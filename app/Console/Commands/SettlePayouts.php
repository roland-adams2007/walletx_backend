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
        $settlementDate = now()->subDay()->toDateString();

        $businessIds = Transaction::where('status', 'success')
            ->where('transaction_type', 'payment')
            ->whereNull('payout_id')
            ->whereDate('paid_at', $settlementDate)
            ->distinct()
            ->pluck('business_id');

        if ($businessIds->isEmpty()) {
            $this->info('Nothing to settle.');
            return self::SUCCESS;
        }

        foreach ($businessIds as $businessId) {
            DB::transaction(function () use ($businessId, $settlementDate) {
                $business = Business::lockForUpdate()->find($businessId);

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
            });
        }

        $this->info('Settlement complete for ' . $businessIds->count() . ' business(es).');

        return self::SUCCESS;
    }
}
