<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\Webhook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SettlePayouts extends Command
{
    protected $signature = 'payouts:settle';

    protected $description = 'Batch daily successful transactions into payouts and process the payout lifecycle';

    const MAX_RETRIES = 3;

    const STUCK_PROCESSING_MINUTES = 30;

    public function handle(): int
    {
        $this->resolveStuckProcessingPayouts();
        $this->retryFailedPayouts();
        $this->batchCreateSettlementPayouts();
        $this->processPendingPayouts();

        return self::SUCCESS;
    }

    protected function resolveStuckProcessingPayouts(): void
    {
        $stuck = Payout::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_PROCESSING_MINUTES))
            ->get();

        foreach ($stuck as $payout) {
            $payout->markAsFailed('Processing timeout');
            $this->dispatchWebhook($payout->business, 'payout.failed', $payout);
        }
    }

    protected function retryFailedPayouts(): void
    {
        $payouts = Payout::where('status', 'failed')
            ->where('retry_count', '<', self::MAX_RETRIES)
            ->get();

        foreach ($payouts as $payout) {
            $payout->incrementRetryCount();
            $this->processPayout($payout);
        }
    }

    protected function processPendingPayouts(): void
    {
        $payouts = Payout::where('status', 'pending')->get();

        foreach ($payouts as $payout) {
            $this->processPayout($payout);
        }
    }

    protected function processPayout(Payout $payout): void
    {
        $payout->markAsProcessing();

        try {
            $response = $this->sendToGateway($payout);

            if ($response['success']) {
                $payout->update([
                    'gateway_reference' => $response['reference'],
                    'gateway_response' => $response,
                ]);
                $payout->markAsSuccessful();
                $this->dispatchWebhook($payout->business, 'payout.success', $payout);
            } else {
                $payout->update(['gateway_response' => $response]);
                $payout->markAsFailed($response['message'] ?? 'Gateway error');
                $this->dispatchWebhook($payout->business, 'payout.failed', $payout);
            }
        } catch (\Throwable $e) {
            $payout->update(['gateway_response' => ['error' => $e->getMessage()]]);
            $payout->markAsFailed($e->getMessage());
            $this->dispatchWebhook($payout->business, 'payout.failed', $payout);
        }
    }

    protected function sendToGateway(Payout $payout): array
    {
        return [
            'success' => true,
            'reference' => (string) Str::uuid(),
            'message' => 'Processed successfully',
        ];
    }

    protected function batchCreateSettlementPayouts(): void
    {
        $businessIds = Transaction::where('status', 'success')
            ->where('transaction_type', 'payment')
            ->whereNull('payout_id')
            ->where('paid_at', '<', now()->startOfDay())
            ->distinct()
            ->pluck('business_id');

        if ($businessIds->isEmpty()) {
            return;
        }

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
                DB::transaction(function () use ($businessId, $settlementDate) {
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
                        'reference' => 'PO_' . strtoupper(Str::random(12)),
                        'business_id' => $business->id,
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
                        ->update(['payout_id' => $payout->id]);

                    $this->dispatchWebhook($business, 'payout.created', $payout);
                });
            }
        }
    }

    protected function dispatchWebhook(Business $business, string $event, Payout $payout): void
    {
        $webhooks = Webhook::where('business_id', $business->id)
            ->where('status', '!=', 'disabled')
            ->get();

        foreach ($webhooks as $webhook) {
            $events = $webhook->events ?? [];

            if (!in_array($event, $events) && !in_array('*', $events)) {
                continue;
            }

            $payload = [
                'event' => $event,
                'data' => [
                    'reference' => $payout->reference,
                    'amount' => $payout->amount,
                    'fee' => $payout->fee,
                    'status' => $payout->status,
                    'business_id' => $business->id,
                ],
            ];

            $signature = hash_hmac('sha256', json_encode($payload), (string) $webhook->secret);

            try {
                $response = Http::withHeaders(['X-Webhook-Signature' => $signature])
                    ->timeout(10)
                    ->post($webhook->url, $payload);

                $webhook->update([
                    'payload' => $payload,
                    'status' => $response->successful() ? 'success' : 'failed',
                ]);
            } catch (\Throwable $e) {
                $webhook->update([
                    'payload' => $payload,
                    'status' => 'failed',
                ]);
            }

            $webhook->incrementDeliveryAttempts();
        }
    }
}
