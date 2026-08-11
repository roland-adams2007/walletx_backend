<?php

namespace App\Jobs;

use App\Mail\CustomerReceiptMail;
use App\Models\EmailLog;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCustomerReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public Transaction $transaction,
    ) {
        $this->onQueue('bulk');
    }

    public function handle(): void
    {
        $preference = $this->transaction->business?->preference;

        if ($preference && !$preference->isReceiptSentToCustomer()) {
            logger()->info('SendCustomerReceipt skipped: business preference disabled', [
                'transaction_id' => $this->transaction->id,
                'business_id' => $this->transaction->business?->id,
            ]);
            return;
        }

        $email = $this->recipientEmail();

        if (!$email) {
            logger()->warning('SendCustomerReceipt skipped: no recipient email', [
                'transaction_id' => $this->transaction->id,
                'customer_id' => $this->transaction->customer_id,
            ]);

            EmailLog::create([
                'mailable' => CustomerReceiptMail::class,
                'recipient_email' => null,
                'subject' => 'Payment Receipt - ' . $this->transaction->reference,
                'loggable_type' => Transaction::class,
                'loggable_id' => $this->transaction->id,
                'failed_at' => now(),
                'failure_reason' => 'No recipient email found on transaction',
            ]);

            return;
        }

        $mail = new CustomerReceiptMail(
            transaction: $this->transaction,
        );

        Mail::to($email)->send($mail);

        EmailLog::create([
            'mailable' => CustomerReceiptMail::class,
            'recipient_email' => $email,
            'subject' => $mail->envelope()->subject,
            'loggable_type' => Transaction::class,
            'loggable_id' => $this->transaction->id,
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        EmailLog::create([
            'mailable' => CustomerReceiptMail::class,
            'recipient_email' => $this->recipientEmail(),
            'subject' => 'Payment Receipt - ' . $this->transaction->reference,
            'loggable_type' => Transaction::class,
            'loggable_id' => $this->transaction->id,
            'failed_at' => now(),
            'failure_reason' => $exception->getMessage(),
        ]);

        logger()->error('SendCustomerReceipt permanently failed', [
            'transaction_id' => $this->transaction->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function recipientEmail(): ?string
    {
        $meta = $this->transaction->meta ?? [];

        return $this->transaction->customer?->email ?? $meta['email'] ?? null;
    }
}
