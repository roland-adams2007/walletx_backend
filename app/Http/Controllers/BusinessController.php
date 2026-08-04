<?php

namespace App\Http\Controllers;



use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function create(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'business_type' => 'required|in:individual,registered',
            'industry' => 'nullable|string|max:255',
        ]);

        $validated['max_balance'] = $validated['business_type'] === 'individual'
            ? 1_000_000_000
            : null;

        $validated['kyc_status'] = $validated['business_type'] === 'registered'
            ? 'verified'
            : 'unverified';

        if ($validated['kyc_status'] === 'verified') {
            $validated['kyc_verified_at'] = now();
        }

        $business = $user->businesses()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Business created successfully',
            'data' => [
                'name'   => $business->name,
                'alt_id' => $business->alt_id,
            ],
        ], 200);
    }

    public function getBusinessDetails(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'alt_id' => 'required|string',
        ]);

        $business = $user->businesses()->with(['logo', 'preference'])->where('alt_id', $request->alt_id)->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'alt_id' => $business->alt_id,
                'name' => $business->name,
                'email' => $business->email,
                'phone' => $business->phone,
                'business_type' => $business->business_type,
                'industry' => $business->industry,
                'logo' => $business->logo?->file_name,
                'max_balance' => $business->max_balance,
                'kyc_status' => $business->kyc_status,
                'kyc_verified_at' => $business->kyc_verified_at,
                'settlement_bank_code' => $business->settlement_bank_code,
                'settlement_account_number' => $business->settlement_account_number,
                'settlement_account_name' => $business->settlement_account_name,
                'is_active' => $business->is_active,
                'last_transaction_at' => $business->last_transaction_at,
                'preference' => [
                    'transaction_receipt_bearer' => $business->preference?->transaction_receipt_bearer,
                    'transaction_fee_bearer' => $business->preference?->transaction_fee_bearer,
                ],
            ],
        ], 200);
    }

    public function getBusinessBalance(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'alt_id' => 'required|string',
        ]);

        $business = $user->businesses()->where('alt_id', $request->alt_id)->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'alt_id' => $business->alt_id,
                'balance' => $business->balance,
                'pending_balance' => $business->pending_balance,
            ],
        ], 200);
    }

    public function upgradeToRegistered(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'alt_id' => 'required|string',
        ]);
        $business = $user->businesses()->where('alt_id', $validated['alt_id'])->first();
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        if ($business->business_type === 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'Business is already registered',
            ], 422);
        }
        $business->update([
            'business_type' => 'registered',
            'max_balance' => null,
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business upgraded to registered successfully',
            'data' => [
                'alt_id' => $business->alt_id,
                'business_type' => $business->business_type,
                'max_balance' => $business->max_balance,
                'kyc_status' => $business->kyc_status,
                'kyc_verified_at' => $business->kyc_verified_at,
            ],
        ], 200);
    }

    public function updateBusinessDetails(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'alt_id' => 'required|string',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'industry' => 'nullable|string|max:255',
            'logo' => 'sometimes|required|integer|exists:uploads,id',
            'settlement_bank_code' => 'sometimes|required|string',
            'settlement_account_number' => 'sometimes|required|string',
            'settlement_account_name' => 'sometimes|required|string',
        ]);

        $business = $user->businesses()->where('alt_id', $validated['alt_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        unset($validated['alt_id']);

        $business->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Business updated successfully',
            'data' => [
                'alt_id' => $business->alt_id,
                'name' => $business->name,
                'email' => $business->email,
                'phone' => $business->phone,
                'industry' => $business->industry,
                'logo' => $business->logo?->file_name,
                'settlement_bank_code' => $business->settlement_bank_code,
                'settlement_account_number' => $business->settlement_account_number,
                'settlement_account_name' => $business->settlement_account_name,
            ],
        ], 200);
    }

    public function updatePreference(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'alt_id' => 'required|string',
            'transaction_receipt_bearer' => 'sometimes|required|in:business,customer',
            'transaction_fee_bearer' => 'sometimes|required|in:business,customer',
        ]);
        $business = $user->businesses()->where('alt_id', $validated['alt_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }
        unset($validated['alt_id']);
        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'No preference fields provided',
            ], 422);
        }
        $business->preference()->update($validated);
        return response()->json([
            'success' => true,
            'message' => 'Preference updated successfully',
            'data' => [
                'transaction_receipt_bearer' => $business->preference->transaction_receipt_bearer,
                'transaction_fee_bearer' => $business->preference->transaction_fee_bearer,
            ],
        ], 200);
    }

    public function rotateApiKeys(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'alt_id' => 'required|string',
        ]);

        $business = $user->businesses()->where('alt_id', $validated['alt_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $rawSecret = 'sk_test_' . Str::random(32);

        $apiKey = $business->apiKeys()
            ->where('environment', 'test')
            ->where('status', 'active')
            ->first();

        if ($apiKey) {
            $apiKey->update([
                'secret_key' => Crypt::encryptString($rawSecret),
            ]);
            $message = 'Secret key rotated successfully.';
        } else {
            $apiKey = $business->apiKeys()->create([
                'environment' => 'test',
                'public_key' => 'pk_test_' . Str::random(24),
                'secret_key' => Crypt::encryptString($rawSecret),
                'status' => 'active',
            ]);

            $message = 'Test API key generated successfully.';
        }
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'public_key' => $apiKey->public_key,
                'secret_key' => $rawSecret,
                'environment' => $apiKey->environment,
            ],
        ], 200);
    }

    public function getApiKeys(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'alt_id' => 'required|string',
        ]);

        $business = $user->businesses()->where('alt_id', $validated['alt_id'])->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $apiKey = $business->apiKeys()
            ->where('environment', 'test')
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            $rawSecret = 'sk_test_' . Str::random(32);

            $apiKey = $business->apiKeys()->create([
                'environment' => 'test',
                'public_key' => 'pk_test_' . Str::random(24),
                'secret_key' => Crypt::encryptString($rawSecret),
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'public_key' => $apiKey->public_key,
                    'secret_key' => $rawSecret,
                    'environment' => $apiKey->environment,
                    'last_used_at' => $apiKey->last_used_at,
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => $apiKey->public_key,
                'secret_key' => Crypt::decryptString($apiKey->secret_key),
                'environment' => $apiKey->environment,
                'last_used_at' => $apiKey->last_used_at,
            ],
        ], 200);
    }
    public function deactivateBusiness(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'alt_id' => 'required|string',
        ]);
        $business = $user->businesses()->where('alt_id', $validated['alt_id'])->first();
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }
        if (!$business->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business is already deactivated',
            ], 422);
        }
        $business->update(['is_active' => false]);
        return response()->json([
            'success' => true,
            'message' => 'Business deactivated successfully',
            'data' => [
                'alt_id' => $business->alt_id,
                'is_active' => $business->is_active,
            ],
        ], 200);
    }
}
