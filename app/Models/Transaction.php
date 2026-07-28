<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'access_code',
        'business_id',
        'counterparty_business_id',
        'initiated_by',
        'type',
        'channel',
        'amount',
        'fee',
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
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'authorization' => 'array',
        'meta' => 'array',
        'paid_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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

    public function markAsSuccessful(): void
    {
        $this->status = 'success';
        $this->paid_at = now();
        $this->save();
    }

    public function markAsFailed(): void
    {
        $this->status = 'failed';
        $this->save();
    }
}
