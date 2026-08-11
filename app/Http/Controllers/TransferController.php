<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function tranferToBank(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'business_id' => 'required|string',
            'reference' => 'required|string|max:100|unique:transactions,reference',
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string',
            'bank_code' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $business = $user->businesses()->where('alt_id', $request->business_id)->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $existing = Transaction::where('reference', $request->reference)
            ->where('business_id', $business->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => [
                    'reference' => $existing->reference,
                    'amount' => $existing->amount,
                    'balance' => $existing->balance_after,
                ],
            ]);
        }

        $amountKobo = (int) round($request->amount * 100);
        try {
            $result = DB::transaction(function () use ($business, $request, $user, $amountKobo) {
                $lockedBusiness = Business::where('id', $business->id)->lockForUpdate()->first();

                if ($lockedBusiness->balance < $amountKobo) {
                    throw new \RuntimeException('Insufficient balance');
                }

                $balanceBefore = $lockedBusiness->balance;
                $fee = 0;
                $netAmount = $amountKobo - $fee;
                $balanceAfter = $balanceBefore - $amountKobo;
                $lockedBusiness->update(['balance' => $balanceAfter]);
                $payout = Payout::create([
                    'reference' => $request->reference,
                    'business_id' => $lockedBusiness->id,
                    'initiated_by' => $user->id,
                    'source' => 'manual',
                    'amount' => $amountKobo,
                    'fee' => $fee,
                    'bank_code' => $request->bank_code,
                    'account_number' => $request->account_number,
                    'account_name' => $request->account_name,
                    'status' => 'processing',
                    'ip_address' => $request->ip(),
                    'device' => $request->header('User-Agent'),
                    'user_agent' => $request->header('User-Agent'),
                ]);

                $transaction = Transaction::create([
                    'reference' => $request->reference,
                    'business_id' => $lockedBusiness->id,
                    'initiated_by' => $user->id,
                    'type' => 'debit',
                    'transaction_type' => 'transfer',
                    'channel' => 'bank',
                    'amount' => $amountKobo,
                    'sub_amount' => $amountKobo,
                    'fee' => $fee,
                    'net_amount' => $netAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'status' => 'success',
                    'description' => "Transfer to {$request->account_name}",
                    'source' => 'dashboard',
                    'ip_address' => $request->ip(),
                    'device' => $request->header('User-Agent'),
                    'user_agent' => $request->header('User-Agent'),
                    'paid_at' => now(),
                    'meta' => [
                        'payout_reference' => $payout->reference,
                        'bank_code' => $request->bank_code,
                        'account_number' => $request->account_number,
                        'account_name' => $request->account_name,
                    ],
                ]);

                return [$transaction, $balanceAfter];
            });

            [$transaction, $balanceAfter] = $result;

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'balance' => $balanceAfter,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transferToBusiness(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'business_id' => 'required|string',
            'target_alt_id' => 'required|string',
            'reference' => 'required|string|max:100|unique:transactions,reference',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($request->business_id === $request->target_alt_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot transfer to the same business',
            ], 422);
        }

        $sourceBusiness = $user->businesses()->where('alt_id', $request->business_id)->first();

        if (!$sourceBusiness) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $targetBusiness = Business::where('alt_id', $request->target_alt_id)->first();

        if (!$targetBusiness) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient business not found',
            ], 404);
        }

        $existing = Transaction::where('reference', $request->reference)
            ->where('business_id', $sourceBusiness->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => [
                    'reference' => $existing->reference,
                    'amount' => $existing->amount,
                    'balance' => $existing->balance_after,
                ],
            ]);
        }

        $amountKobo = (int) round($request->amount * 100);

        try {
            $result = DB::transaction(function () use ($sourceBusiness, $targetBusiness, $user, $amountKobo, $request) {
                $ids = [$sourceBusiness->id, $targetBusiness->id];
                sort($ids);
                $locked = Business::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

                $source = $locked[$sourceBusiness->id];
                $target = $locked[$targetBusiness->id];

                if ($source->balance < $amountKobo) {
                    throw new \RuntimeException('Insufficient balance');
                }

                $sourceBalanceBefore = $source->balance;
                $sourceBalanceAfter = $sourceBalanceBefore - $amountKobo;
                $targetBalanceBefore = $target->balance;
                $targetBalanceAfter = $targetBalanceBefore + $amountKobo;

                $source->update(['balance' => $sourceBalanceAfter]);
                $target->update(['balance' => $targetBalanceAfter]);

                $debit = Transaction::create([
                    'reference' => $request->reference,
                    'business_id' => $source->id,
                    'counterparty_business_id' => $target->id,
                    'initiated_by' => $user->id,
                    'type' => 'debit',
                    'transaction_type' => 'business_transfer',
                    'channel' => 'internal',
                    'amount' => $amountKobo,
                    'sub_amount' => $amountKobo,
                    'fee' => 0,
                    'net_amount' => $amountKobo,
                    'balance_before' => $sourceBalanceBefore,
                    'balance_after' => $sourceBalanceAfter,
                    'status' => 'success',
                    'description' => "Transfer to {$target->name}",
                    'source' => 'dashboard',
                    'paid_at' => now(),
                    'meta' => [
                        'target_alt_id' => $target->alt_id,
                        'target_name' => $target->name,
                    ],
                ]);

                Transaction::create([
                    'reference' => $request->reference . '-C',
                    'business_id' => $target->id,
                    'counterparty_business_id' => $source->id,
                    'initiated_by' => $user->id,
                    'type' => 'credit',
                    'transaction_type' => 'business_transfer',
                    'channel' => 'internal',
                    'amount' => $amountKobo,
                    'sub_amount' => $amountKobo,
                    'fee' => 0,
                    'net_amount' => $amountKobo,
                    'balance_before' => $targetBalanceBefore,
                    'balance_after' => $targetBalanceAfter,
                    'status' => 'success',
                    'description' => "Transfer from {$source->name}",
                    'source' => 'dashboard',
                    'paid_at' => now(),
                    'meta' => [
                        'source_alt_id' => $source->alt_id,
                        'source_name' => $source->name,
                    ],
                ]);

                return [$debit, $sourceBalanceAfter];
            });

            [$transaction, $balanceAfter] = $result;

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => [
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'balance' => $balanceAfter,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
