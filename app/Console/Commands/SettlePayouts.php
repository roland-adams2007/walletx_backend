<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettlePayouts extends Command
{
    protected $signature = 'payouts:settle';

    protected $description = 'Batch every business daily successful payment transactions into a single payout';

    public function handle(): int
    {
        $businessIds = Transaction::where('status', 'success')
            ->where('transaction_type', 'payment')
            ->whereNull('payout_id')
            ->where('paid_at', '<', now()->startOfDay())
            ->distinct()
            ->pluck('business_id');

        if ($businessIds->isEmpty()) {
            $this->info('Nothing to settle.');
            return self::SUCCESS;
        }

        $payoutsCreated = 0;

        foreach ($businessIds as $businessId) {
            $settlementDates = Transaction::where('business_id', $businessId)
                ->where('status', 'success')
                ->where('transaction_type', 'payment')
                ->whereNull('payout_id')
                ->where('paid_at', '<', now()->startOfDay())
                ->selectRaw('DATE(paid_at) as settlement_date')
                ->distinct()
                ->pluck('settlement_date');

            foreach ($settlementDates as $settlementDate) {
                DB::transaction(function () use ($businessId, $settlementDate, &$payoutsCreated) {
                    $business = Business::lockForUpdate()->find($businessId);

                    if (!$business) {
                        return;
                    }

                    $transactions = Transaction::where('business_id', $businessId)
                        ->where('status', 'success')
                        ->where('transaction_type', 'payment')
                        ->whereNull('payout_id')
                        ->whereDate('paid_at', $settlementDate)
                        ->lockForUpdate()
                        ->get();

                    if ($transactions->isEmpty()) {
                        return;
                    }

                    $totalNet = $transactions->sum('net_amount');
                    $totalFee = $transactions->sum('fee');

                    if ($totalNet <= 0) {
                        return;
                    }

                    $payout = Payout::create([
                        'reference' => 'po_' . Str::random(16),
                        'business_id' => $business->id,
                        'initiated_by' => null,
                        'source' => 'automatic',
                        'amount' => $totalNet,
                        'fee' => $totalFee,
                        'bank_code' => $business->settlement_bank_code,
                        'account_number' => $business->settlement_account_number,
                        'account_name' => $business->settlement_account_name,
                        'narration' => 'Settlement for ' . $settlementDate,
                        'status' => 'pending',
                    ]);

                    Transaction::whereIn('id', $transactions->pluck('id'))
                        ->update([
                            'payout_id' => $payout->id,
                        ]);

                    $payoutsCreated++;
                });
            }
        }

        $this->info("Settlement complete. {$payoutsCreated} payout(s) created.");

        return self::SUCCESS;
    }
}