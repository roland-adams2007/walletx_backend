<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'access_code',
        'business_id',
        'customer_id',
        'counterparty_business_id',
        'initiated_by',
        'type',
        'transaction_type',
        'channel',
        'sub_amount',
        'amount',
        'fee',
        'net_amount',
        'balance_before',
        'balance_after',
        'status',
        'description',
        'gateway_response',
        'authorization',
        'meta',
        'api_key_id',
        'source',
        'ip_address',
        'device',
        'user_agent',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'sub_amount' => 'integer',
        'amount' => 'integer',
        'fee' => 'integer',
        'net_amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'authorization' => 'array',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function counterpartyBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'counterparty_business_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markAsSuccessful(): void
    {
        $this->update([
            'status' => 'success',
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    public function markAsReversed(): void
    {
        $this->update([
            'status' => 'reversed',
        ]);
    }
}
