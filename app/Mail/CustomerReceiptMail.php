<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Receipt - ' . $this->transaction->reference,
        );
    }

    public function content(): Content
    {
        $meta = $this->transaction->meta ?? [];
        $name = trim(($meta['firstname'] ?? '') . ' ' . ($meta['lastname'] ?? ''));

        return new Content(
            view: 'emails.receipts.customer',
            with: [
                'transaction' => $this->transaction,
                'business' => $this->transaction->business,
                'customerName' => $name !== '' ? $name : 'Customer',
                'amount' => number_format($this->transaction->amount / 100, 2),
                'subAmount' => number_format($this->transaction->sub_amount / 100, 2),
                'fee' => number_format($this->transaction->fee / 100, 2),
                'reference' => $this->transaction->reference,
                'date' => $this->transaction->paid_at?->format('jS M, Y'),
                'channel' => $this->transaction->channel,
                'authorization' => $this->transaction->authorization,
            ],
        );
    }
}
