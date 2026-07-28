<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InlineController extends Controller
{
    /**
     * Resolves the business from the key, creates (or reuses) the pending
     * transaction, and hands back everything the widget needs in one shot —
     * including merchant name/logo — so there's no second round-trip.
     */
    public function initialise(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'email' => 'required|email',
            'amount' => 'required|integer|min:100',
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'ref' => 'required|string|max:100',
            'meta' => 'nullable|array',
        ]);

        $apiKey = ApiKey::where('public_key', $validated['key'])
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive public key',
            ], 401);
        }

        $business = $apiKey->business;

        if (!$business || !$business->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business is not active',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $apiKey, $business, $request) {
            $existing = Transaction::where('reference', $validated['ref'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->business_id !== $business->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reference already used by another business',
                    ], 409);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction already initialised',
                    'data' => [
                        'reference' => $existing->reference,
                        'amount' => $existing->amount,
                        'status' => $existing->status,
                        'merchant' => [
                            'name' => $business->name,
                            'logo' => $business->logo_url,
                        ],
                    ],
                ], 200);
            }

            $businessRow = Business::where('id', $business->id)->lockForUpdate()->first();

            $transaction = $businessRow->transactions()->create([
                'reference' => $validated['ref'],
                'access_code' => Str::random(20),
                'type' => 'credit',
                'channel' => 'card',
                'amount' => $validated['amount'],
                'fee' => 0,
                'balance_before' => $businessRow->balance,
                'balance_after' => $businessRow->balance,
                'status' => 'pending',
                'meta' => array_merge($validated['meta'] ?? [], [
                    'email' => $validated['email'],
                    'firstname' => $validated['firstname'],
                    'lastname' => $validated['lastname'],
                    'phone' => $validated['phone'] ?? null,
                ]),
                'api_key_id' => $apiKey->id,
                'source' => 'api',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction initialised',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'access_code' => $transaction->access_code,
                    'merchant' => [
                        'name' => $businessRow->name,
                        'logo' => $businessRow->logo_url,
                    ],
                ],
            ], 200);
        });
    }

    /**
     * Handles both card and bank transfer. `channel` picks the path,
     * `simulate` drives the outcome for either one in test mode.
     */
    public function charge(Request $request)
    {
        $validated = $request->validate([
            'public_key' => 'required|string',
            'reference' => 'required|string',
            'channel' => 'required|in:card,bank_transfer',
            'card_number' => 'required_if:channel,card|string',
            'expiry_month' => 'required_if:channel,card|string|size:2',
            'expiry_year' => 'required_if:channel,card|string|size:2',
            'cvv' => 'required_if:channel,card|string|size:3',
            'simulate' => 'nullable|in:success,failed,pending',
        ]);

        $apiKey = ApiKey::where('public_key', $validated['public_key'])
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive public key',
            ], 401);
        }

        return DB::transaction(function () use ($validated, $apiKey) {
            $transaction = Transaction::where('reference', $validated['reference'])
                ->where('business_id', $apiKey->business_id)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            if ($transaction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction is no longer pending',
                ], 422);
            }

            $channel = $validated['channel'];
            $simulate = $validated['simulate'] ?? null;

            if ($simulate === 'pending') {
                $transaction->update([
                    'channel' => $channel,
                    'gateway_response' => 'Simulated pending (test mode)',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment pending',
                    'data' => [
                        'reference' => $transaction->reference,
                        'amount' => $transaction->amount,
                        'status' => 'pending',
                    ],
                ], 200);
            }

            if ($simulate === 'failed') {
                $transaction->update([
                    'status' => 'failed',
                    'channel' => $channel,
                    'gateway_response' => 'Simulated failure (test mode)',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Simulated failure (test mode)',
                ], 422);
            }

            $authorization = null;

            if ($channel === 'card') {
                if ($simulate === 'success') {
                    $cardResult = [
                        'success' => true,
                        'brand' => $this->detectCardBrand($validated['card_number']),
                    ];
                } else {
                    $cardResult = $this->processCard($validated);
                }

                if (!$cardResult['success']) {
                    $transaction->update([
                        'status' => 'failed',
                        'channel' => 'card',
                        'gateway_response' => $cardResult['message'] ?? 'Card declined',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $cardResult['message'] ?? 'Card declined',
                    ], 422);
                }

                $authorization = [
                    'last4' => substr($validated['card_number'], -4),
                    'brand' => $cardResult['brand'],
                    'exp_month' => $validated['expiry_month'],
                    'exp_year' => $validated['expiry_year'],
                ];
            } else {
                if ($simulate !== 'success') {
                    $transferReceived = $this->checkBankTransfer($transaction);

                    if (!$transferReceived) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Transfer not yet confirmed. Please wait a moment and try again.',
                        ], 422);
                    }
                }
            }

            $business = Business::where('id', $transaction->business_id)->lockForUpdate()->first();

            $transaction->update([
                'status' => 'success',
                'channel' => $channel,
                'balance_before' => $business->balance,
                'balance_after' => $business->balance + $transaction->amount,
                'gateway_response' => $channel === 'card' ? 'Approved' : 'Transfer confirmed',
                'authorization' => $authorization,
                'paid_at' => now(),
            ]);

            $business->update([
                'balance' => $business->balance + $transaction->amount,
                'last_transaction_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                ],
            ], 200);
        });
    }

    /**
     * Verify: takes only a reference. No public_key, no simulate.
     * Pure lookup — brings back the current state of that transaction,
     * nothing gets mutated here.
     */
    public function verify(Request $request, string $reference)
    {
        $transaction = Transaction::where('reference', $reference)->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction retrieved',
            'data' => [
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'channel' => $transaction->channel,
                'gateway_response' => $transaction->gateway_response,
                'paid_at' => $transaction->paid_at,
            ],
        ], 200);
    }

    private function detectCardBrand(string $cardNumber): string
    {
        $digits = preg_replace('/\D/', '', $cardNumber);

        if (preg_match('/^4/', $digits)) return 'VISA';
        if (preg_match('/^5[1-5]/', $digits) || preg_match('/^2(2[2-9]|[3-6]\d|7[01])/', $digits)) return 'MASTERCARD';
        if (preg_match('/^506(0|1)|^5078/', $digits)) return 'VERVE';

        return 'UNKNOWN';
    }

    private function processCard(array $card): array
    {
        // Placeholder: no real processor wired in yet.
        // Replace this with an actual card network / processor integration
        // before this handles real money.
        return [
            'success' => true,
            'brand' => 'VISA',
        ];
    }

    private function checkBankTransfer(Transaction $transaction): bool
    {
        // Placeholder: no real bank/settlement integration wired in yet.
        // Replace with an actual check against your virtual account provider.
        return false;
    }
}
