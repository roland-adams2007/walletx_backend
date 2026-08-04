<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'send_receipt_to_business',
        'send_receipt_to_customer',
        'charge_fee_to_customer',
    ];

    protected $casts = [
        'send_receipt_to_business' => 'boolean',
        'send_receipt_to_customer' => 'boolean',
        'charge_fee_to_customer' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
    
    public function isReceiptSentToBusiness(): bool
    {
        return $this->send_receipt_to_business;
    }

    public function isReceiptSentToCustomer(): bool
    {
        return $this->send_receipt_to_customer;
    }

    public function isFeeChargedToCustomer(): bool
    {
        return $this->charge_fee_to_customer;
    }

    public function isFeeChargedToBusiness(): bool
    {
        return !$this->charge_fee_to_customer;
    }

    public function getReceiptRecipients(): array
    {
        $recipients = [];

        if ($this->send_receipt_to_business) {
            $recipients[] = 'business';
        }

        if ($this->send_receipt_to_customer) {
            $recipients[] = 'customer';
        }

        return $recipients;
    }

    public function getFeeBearer(): string
    {
        return $this->charge_fee_to_customer ? 'customer' : 'business';
    }
}
