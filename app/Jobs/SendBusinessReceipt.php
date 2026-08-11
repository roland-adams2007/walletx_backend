<?php

namespace App\Jobs;

use App\Mail\BusinessReceiptMail;
use App\Models\EmailLog;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBusinessReceipt implements ShouldQueue
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
        $business = $this->transaction->business;
        $preference = $business?->preference;

        if ($preference && !$preference->isReceiptSentToBusiness()) {
            logger()->info('SendBusinessReceipt skipped: business preference disabled', [
                'transaction_id' => $this->transaction->id,
                'business_id' => $business?->id,
            ]);
            return;
        }

        $recipients = $this->recipientEmails();

        if (empty($recipients)) {
            logger()->warning('SendBusinessReceipt skipped: no recipient email found', [
                'transaction_id' => $this->transaction->id,
                'business_id' => $business?->id,
            ]);

            EmailLog::create([
                'mailable' => BusinessReceiptMail::class,
                'recipient_email' => null,
                'subject' => 'Payment Received - ' . $this->transaction->reference,
                'loggable_type' => Transaction::class,
                'loggable_id' => $this->transaction->id,
                'failed_at' => now(),
                'failure_reason' => 'No business or owner email found on transaction',
            ]);

            return;
        }

        $mail = new BusinessReceiptMail(
            transaction: $this->transaction,
        );

        Mail::to($recipients)->send($mail);

        EmailLog::create([
            'mailable' => BusinessReceiptMail::class,
            'recipient_email' => implode(', ', $recipients),
            'subject' => $mail->envelope()->subject,
            'loggable_type' => Transaction::class,
            'loggable_id' => $this->transaction->id,
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        EmailLog::create([
            'mailable' => BusinessReceiptMail::class,
            'recipient_email' => implode(', ', $this->recipientEmails()),
            'subject' => 'Payment Received - ' . $this->transaction->reference,
            'loggable_type' => Transaction::class,
            'loggable_id' => $this->transaction->id,
            'failed_at' => now(),
            'failure_reason' => $exception->getMessage(),
        ]);

        logger()->error('SendBusinessReceipt permanently failed', [
            'transaction_id' => $this->transaction->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Business email + business owner's email, deduplicated.
     *
     * @return array<int, string>
     */
    private function recipientEmails(): array
    {
        $business = $this->transaction->business;

        if (!$business) {
            return [];
        }

        $emails = array_filter([
            $business->email,
            $business->owner?->email,
        ]);

        return array_values(array_unique($emails));
    }
}
