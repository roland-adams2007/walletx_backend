<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Received - ' . $this->transaction->reference,
        );
    }

    public function content(): Content
    {
        $meta = $this->transaction->meta ?? [];
        $name = trim(($meta['firstname'] ?? '') . ' ' . ($meta['lastname'] ?? ''));
        $email = $this->transaction->customer?->email ?? $meta['email'] ?? null;

        return new Content(
            view: 'emails.receipts.business',
            with: [
                'transaction' => $this->transaction,
                'business' => $this->transaction->business,
                'customerName' => $name !== '' ? $name : 'Customer',
                'customerEmail' => $email,
                'amount' => number_format($this->transaction->amount / 100, 2),
                'netAmount' => number_format($this->transaction->net_amount / 100, 2),
                'fee' => number_format($this->transaction->fee / 100, 2),
                'reference' => $this->transaction->reference,
                'date' => $this->transaction->paid_at?->format('jS M, Y'),
                'channel' => $this->transaction->channel,
                'authorization' => $this->transaction->authorization,
            ],
        );
    }
}
