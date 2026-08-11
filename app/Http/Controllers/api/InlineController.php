<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBusinessReceipt;
use App\Jobs\SendCustomerReceipt;
use App\Jobs\SendWebhook;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InlineController extends Controller
{
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

            $customerExist = Customer::where('email', $validated['email'])
                ->where('business_id', $business->id)
                ->first();

            if (!$customerExist) {
                $customer = Customer::create([
                    'business_id' => $business->id,
                    'cus_id' => 'cus_' . Str::random(12),
                    'email' => $validated['email'],
                    'firstname' => null,
                    'lastname' => null,
                    'phone' => null,
                ]);
            } else {
                $customer = $customerExist;
            }

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

                if (!$existing->isPending()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This reference has already been used and cannot be reinitialised. Use a new reference.',
                    ], 409);
                }

                if ($existing->isExpired()) {
                    $existing->update([
                        'expires_at' => now()->addMinutes(30),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction already initialised',
                    'data' => [
                        'reference' => $existing->reference,
                        'sub_amount' => $existing->sub_amount,
                        'fee' => $existing->fee,
                        'amount' => $existing->amount,
                        'net_amount' => $existing->net_amount,
                        'status' => $existing->status,
                        'access_code' => $existing->access_code,
                        'expires_at' => $existing->expires_at?->toIso8601String(),
                        'merchant' => [
                            'name' => $business->name,
                            'logo' => $business->logo_url,
                        ],
                    ],
                ], 200);
            }

            $businessRow = Business::where('id', $business->id)->lockForUpdate()->first();

            $subAmount = $validated['amount'];

            if ($businessRow->max_balance > 0 && $subAmount > $businessRow->max_balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount exceeds the maximum transaction limit allowed for this business',
                ], 422);
            }

            $preference = $businessRow->preference;
            $chargeFeeToCustomer = $preference ? $preference->isFeeChargedToCustomer() : false;

            $feePercent = (float) get_setting('transaction_fee_percent', 0);
            $feeMaxCap = (int) get_setting('transaction_fee_max_cap', 0);

            $fee = (int) round($subAmount * $feePercent / 100);

            if ($feeMaxCap > 0 && $fee > $feeMaxCap) {
                $fee = $feeMaxCap;
            }

            if ($chargeFeeToCustomer) {
                $amountToCharge = $subAmount + $fee;
                $netAmount = $subAmount;
            } else {
                $amountToCharge = $subAmount;
                $netAmount = $subAmount - $fee;
            }

            $transaction = $businessRow->transactions()->create([
                'reference' => $validated['ref'],
                'access_code' => Str::random(20),
                'customer_id' => $customer->id,
                'type' => 'credit',
                'channel' => 'card',
                'sub_amount' => $subAmount,
                'amount' => $amountToCharge,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'balance_before' => $businessRow->balance,
                'balance_after' => $businessRow->balance,
                'status' => 'pending',
                'meta' => array_merge($validated['meta'] ?? [], [
                    'email' => $validated['email'],
                    'firstname' => $validated['firstname'],
                    'lastname' => $validated['lastname'],
                    'phone' => $validated['phone'] ?? null,
                    'charge_fee_to_customer' => $chargeFeeToCustomer,
                ]),
                'api_key_id' => $apiKey->id,
                'source' => 'api',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => now()->addMinutes(30),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction initialised',
                'data' => [
                    'reference' => $transaction->reference,
                    'sub_amount' => $transaction->sub_amount,
                    'fee' => $transaction->fee,
                    'amount' => $transaction->amount,
                    'net_amount' => $transaction->net_amount,
                    'charge_fee_to_customer' => $chargeFeeToCustomer,
                    'access_code' => $transaction->access_code,
                    'expires_at' => $transaction->expires_at,
                    'merchant' => [
                        'name' => $businessRow->name,
                        'logo' => $businessRow->logo_url,
                    ],
                ],
            ], 200);
        });
    }

    public function charge(Request $request)
    {
        $validated = $request->validate([
            'public_key' => 'nullable|string',
            'access_code' => 'nullable|string',
            'reference' => 'required|string',
            'channel' => 'required|in:card,bank_transfer',
            'card_number' => 'required_if:channel,card|string',
            'expiry_month' => 'required_if:channel,card|string|size:2',
            'expiry_year' => 'required_if:channel,card|string|size:2',
            'cvv' => 'required_if:channel,card|string|size:3',
            'simulate' => 'nullable|in:success,failed,pending',
        ]);

        if (empty($validated['public_key']) && empty($validated['access_code'])) {
            return response()->json([
                'success' => false,
                'message' => 'public_key or access_code is required',
            ], 401);
        }

        $apiKey = null;
        $businessId = null;

        if (!empty($validated['public_key'])) {
            $apiKey = ApiKey::where('public_key', $validated['public_key'])
                ->where('status', 'active')
                ->first();

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or inactive public key',
                ], 401);
            }

            $businessId = $apiKey->business_id;
        }

        return DB::transaction(function () use ($validated, $apiKey, $businessId) {
            $query = Transaction::where('reference', $validated['reference'])->lockForUpdate();

            if (!empty($validated['access_code'])) {
                $query->where('access_code', $validated['access_code']);
            }

            if ($businessId) {
                $query->where('business_id', $businessId);
            }

            $transaction = $query->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            $apiKey = $apiKey ?: $transaction->apiKey;

            if ($transaction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction is no longer pending',
                ], 422);
            }

            if ($transaction->isExpired()) {
                $transaction->update([
                    'status' => 'failed',
                    'gateway_response' => 'Transaction expired',
                ]);

                if ($apiKey) {
                    $this->dispatchWebhook($apiKey, 'charge.failed', $this->webhookPayload($transaction));
                }

                return response()->json([
                    'success' => false,
                    'message' => 'This transaction has expired',
                ], 422);
            }

            $channel = $validated['channel'];
            $simulate = $validated['simulate'] ?? null;

            if ($simulate === 'pending') {
                $transaction->update([
                    'channel' => $channel,
                    'gateway_response' => 'Simulated pending (test mode)',
                ]);

                if ($apiKey) {
                    $this->dispatchWebhook($apiKey, 'charge.pending', $this->webhookPayload($transaction));
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment pending',
                    'data' => [
                        'reference' => $transaction->reference,
                        'amount' => $transaction->amount / 100,
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

                if ($apiKey) {
                    $this->dispatchWebhook($apiKey, 'charge.failed', $this->webhookPayload($transaction));
                }

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

                    if ($apiKey) {
                        $this->dispatchWebhook($apiKey, 'charge.failed', $this->webhookPayload($transaction));
                    }

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
                'gateway_response' => $channel === 'card' ? 'Approved' : 'Transfer confirmed',
                'authorization' => $authorization,
                'paid_at' => now(),
            ]);

            $business->increment('pending_balance', $transaction->net_amount);
            $preference = $business->preference;

            if ($preference && $preference->isReceiptSentToBusiness()) {
                SendBusinessReceipt::dispatch($transaction);
            }

            if ($preference && $preference->isReceiptSentToCustomer()) {
                SendCustomerReceipt::dispatch($transaction);
            }

            if ($apiKey) {
                $this->dispatchWebhook($apiKey, 'charge.success', $this->webhookPayload($transaction));
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount / 100,
                    'status' => $transaction->status,
                ],
            ], 200);
        });
    }

    public function verify(Request $request, string $reference)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization header missing or malformed',
            ], 401);
        }

        $apiKeyValue = trim(substr($authHeader, 7));

        if (empty($apiKeyValue)) {
            return response()->json([
                'success' => false,
                'message' => 'Secret key is required',
            ], 401);
        }

        if (!str_starts_with($apiKeyValue, 'sk_live_')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid secret key format',
            ], 401);
        }

        $keyId = substr($apiKeyValue, 8, 6);
        $secretKey = substr($apiKeyValue, 14);

        if (strlen($keyId) !== 6 || empty($secretKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid secret key format',
            ], 401);
        }

        $apiKey = ApiKey::where('key_id', $keyId)
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid secret key',
            ], 401);
        }

        try {
            $storedSecret = Crypt::decryptString($apiKey->secret_key);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid secret key',
            ], 401);
        }

        if (!hash_equals($storedSecret, $apiKeyValue)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid secret key',
            ], 401);
        }

        $transaction = Transaction::where('reference', $reference)
            ->where('business_id', $apiKey->business_id)
            ->first();

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
                'amount' => $transaction->amount / 100,
                'status' => $transaction->status,
                'channel' => $transaction->channel,
                'gateway_response' => $transaction->gateway_response,
                'paid_at' => $transaction->paid_at,
            ],
        ], 200);
    }

    private function dispatchWebhook(ApiKey $apiKey, string $event, array $payload): void
    {
        if (empty($apiKey->webhook_url)) {
            return;
        }

        $webhook = Webhook::create([
            'business_id' => $apiKey->business_id,
            'url' => $apiKey->webhook_url,
            'secret' => $apiKey->secret_key,
            'events' => [$event],
            'status' => 'pending',
        ]);

        SendWebhook::dispatch($webhook->id, $event, $payload);
    }

    private function webhookPayload(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'sub_amount' => $transaction->sub_amount / 100,
            'fee' => $transaction->fee / 100,
            'amount' => $transaction->amount / 100,
            'net_amount' => $transaction->net_amount / 100,
            'status' => $transaction->status,
            'channel' => $transaction->channel,
            'gateway_response' => $transaction->gateway_response,
            'paid_at' => $transaction->paid_at,
            'customer_email' => $transaction->customer?->email,
        ];
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
        return [
            'success' => true,
            'brand' => 'VISA',
        ];
    }

    private function checkBankTransfer(Transaction $transaction): bool
    {
        return false;
    }
}