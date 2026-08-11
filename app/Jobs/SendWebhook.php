<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public int $webhookId,
        public string $event,
        public array $payload
    ) {
        $this->onQueue('high');
    }

    /**
     * Shown in Horizon / `queue:work` output instead of the generic class name.
     */
    public function displayName(): string
    {
        return "webhook:{$this->event}:{$this->webhookId}";
    }

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook) {
            return;
        }

        $webhook->incrementDeliveryAttempts();

        $body = [
            'event' => $this->event,
            'data' => $this->payload,
        ];

        // Encode once and sign/send the exact same bytes, so the signature
        // always matches what the receiving endpoint actually gets.
        $payloadJson = json_encode($body);
        $signature = hash_hmac('sha512', $payloadJson, $webhook->secret ?? '');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => $signature,
        ])->timeout(15)->withBody($payloadJson, 'application/json')->post($webhook->url);

        if ($response->successful()) {
            $webhook->update(['status' => 'success']);
            return;
        }

        $webhook->update(['status' => 'failed']);

        // Throw (don't call $this->fail()) so the queue worker retries
        // according to $tries/$backoff. $this->fail() would end the job
        // immediately and skip the remaining attempts.
        throw new \RuntimeException(
            "Webhook {$this->webhookId} endpoint responded with {$response->status()}"
        );
    }

    /**
     * Called once all retry attempts are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $webhook = Webhook::find($this->webhookId);

        if ($webhook) {
            $webhook->update(['status' => 'failed']);
        }

        Log::warning('Webhook delivery permanently failed', [
            'webhook_id' => $this->webhookId,
            'event' => $this->event,
            'error' => $exception?->getMessage(),
        ]);
    }
}
