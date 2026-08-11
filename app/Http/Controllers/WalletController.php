<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    private function resolveBusiness(Request $request): ?Business
    {
        $user = $request->user();
        $businessId = $request->input('business_id');

        if ($businessId) {
            return $user->businesses()->where('alt_id', $businessId)->first();
        }
        return $user->businesses()->first();
    }


    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:100',
            'ref' => 'nullable|string|max:100',
            'business_id' => 'nullable|integer',
        ]);

        $business = $this->resolveBusiness($request);

        if (!$business || !$business->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business is not active',
            ], 403);
        }

        $reference = $validated['ref'] ?? ('wallet_' . Str::random(16));

        return DB::transaction(function () use ($validated, $business, $reference, $request) {
            $existing = Transaction::where('reference', $reference)
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
                    $existing->update(['expires_at' => now()->addMinutes(30)]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction already initialised',
                    'data' => [
                        'reference' => $existing->reference,
                        'amount' => $existing->amount,
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
            $amount = $validated['amount'];
            $selfCustomer = Customer::firstOrCreate(
                ['business_id' => $businessRow->id, 'email' => $request->user()->email],
                ['cus_id' => 'cus_' . Str::random(12), 'firstname' => null, 'lastname' => null, 'phone' => null]
            );

            $transaction = $businessRow->transactions()->create([
                'reference' => $reference,
                'access_code' => Str::random(20),
                'customer_id' => $selfCustomer->id,
                'type' => 'credit',
                'channel' => 'card',
                'sub_amount' => $amount,
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'balance_before' => $businessRow->balance,
                'balance_after' => $businessRow->balance,
                'status' => 'pending',
                'meta' => [
                    'email' => $request->user()->email,
                ],
                'api_key_id' => null, 
                'source' => 'dashboard',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => now()->addMinutes(30),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction initialised',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
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
            'reference' => 'required|string',
            'channel' => 'required|in:card,bank_transfer',
            'card_number' => 'required_if:channel,card|string',
            'expiry_month' => 'required_if:channel,card|string|size:2',
            'expiry_year' => 'required_if:channel,card|string|size:2',
            'cvv' => 'required_if:channel,card|string|size:3',
            'simulate' => 'nullable|in:success,failed,pending',
        ]);

        $businessIds = $request->user()->businesses()->pluck('id');

        if ($businessIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $businessIds) {
            $transaction = Transaction::where('reference', $validated['reference'])
                ->whereIn('business_id', $businessIds)
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

            if ($transaction->isExpired()) {
                $transaction->update([
                    'status' => 'failed',
                    'gateway_response' => 'Transaction expired',
                ]);

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
                $authorization = [
                    'last4' => substr($validated['card_number'], -4),
                    'brand' => 'VISA',
                    'exp_month' => $validated['expiry_month'],
                    'exp_year' => $validated['expiry_year'],
                ];
            }

            $businessRow = Business::where('id', $transaction->business_id)->lockForUpdate()->first();

            $transaction->update([
                'status' => 'success',
                'channel' => $channel,
                'gateway_response' => $channel === 'card' ? 'Approved' : 'Transfer confirmed',
                'authorization' => $authorization,
                'paid_at' => now(),
            ]);
            $businessRow->increment('balance', $transaction->net_amount);

            return response()->json([
                'success' => true,
                'message' => 'Wallet funded',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                ],
            ], 200);
        });
    }

    public function verify(Request $request, string $reference)
    {
        $businessIds = $request->user()->businesses()->pluck('id');

        $transaction = Transaction::where('reference', $reference)
            ->whereIn('business_id', $businessIds)
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
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'channel' => $transaction->channel,
                'gateway_response' => $transaction->gateway_response,
                'paid_at' => $transaction->paid_at,
            ],
        ], 200);
    }
}
