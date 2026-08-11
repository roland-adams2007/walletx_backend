<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;


class RedirectController extends Controller
{
    public function initialise(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'amount' => 'required|numeric|min:1',
            'reference' => 'required|string|max:100',
            'metadata' => 'nullable|array',
            'callback_url' => 'required|url',
            'firstname' => 'nullable|string|max:100',
            'lastname' => 'nullable|string|max:100',
        ]);

        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'status' => false,
                'message' => 'Authorization header missing or malformed',
            ], 401);
        }

        $apiKeyValue = trim(substr($authHeader, 7));

        if (empty($apiKeyValue)) {
            return response()->json([
                'status' => false,
                'message' => 'Secret key is required',
            ], 401);
        }

        if (!str_starts_with($apiKeyValue, 'sk_live_')) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid secret key format',
            ], 401);
        }

        $keyId = substr($apiKeyValue, 8, 6);
        $secretKey = substr($apiKeyValue, 14);

        if (strlen($keyId) !== 6 || empty($secretKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid secret key format',
            ], 401);
        }

        $apiKey = ApiKey::where('key_id', $keyId)
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid secret key',
            ], 401);
        }

        try {
            $storedSecret = Crypt::decryptString($apiKey->secret_key);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid secret key',
            ], 401);
        }

        if (!hash_equals($storedSecret, $apiKeyValue)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid secret key',
            ], 401);
        }

        $business = $apiKey->business;

        if (!$business || !$business->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Business is not active',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $apiKey, $business, $request) {

            $customer = Customer::where('email', $validated['email'])
                ->where('business_id', $business->id)
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'business_id' => $business->id,
                    'cus_id' => 'cus_' . Str::random(12),
                    'email' => $validated['email'],
                    'firstname' => null,
                    'lastname' => null,
                    'phone' => null,
                ]);
            }

            $existing = Transaction::where('reference', $validated['reference'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->business_id !== $business->id) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Reference already used by another business',
                    ], 409);
                }

                if (!$existing->isPending()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'This reference has already been used and cannot be reinitialised. Use a new reference.',
                    ], 409);
                }

                if ($existing->isExpired()) {
                    $existing->update([
                        'expires_at' => now()->addMinutes(30),
                    ]);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Authorization URL created',
                    'data' => [
                        'authorization_url' => $this->buildCheckoutUrl($existing->access_code),
                        'access_code' => $existing->access_code,
                        'reference' => $existing->reference,
                    ],
                ], 200);
            }

            $businessRow = Business::where('id', $business->id)->lockForUpdate()->first();

            $subAmount = $validated['amount'];

            if ($businessRow->max_balance > 0 && $subAmount > $businessRow->max_balance) {
                return response()->json([
                    'status' => false,
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

            $accessCode = Str::random(20);

            $transaction = $businessRow->transactions()->create([
                'reference' => $validated['reference'],
                'access_code' => $accessCode,
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
                'meta' => array_merge($validated['metadata'] ?? [], [
                    'email' => $validated['email'],
                    'firstname' => $validated['firstname'] ?? null,
                    'lastname' => $validated['lastname'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'charge_fee_to_customer' => $chargeFeeToCustomer,
                    'callback_url' => $validated['callback_url'],
                ]),
                'api_key_id' => $apiKey->id,
                'source' => 'api',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => now()->addMinutes(30),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => $this->buildCheckoutUrl($accessCode),
                    'access_code' => $accessCode,
                    'reference' => $transaction->reference,
                ],
            ], 200);
        });
    }

    protected function buildCheckoutUrl(string $accessCode): string
    {
        return getBaseURL() . '/checkout/' . $accessCode;
    }
}
