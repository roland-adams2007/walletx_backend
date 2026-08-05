<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'business_id',
        'initiated_by',
        'source',
        'amount',
        'fee',
        'bank_code',
        'account_number',
        'account_name',
        'narration',
        'status',
        'gateway_reference',
        'gateway_response',
        'failure_reason',
        'retry_count',
        'meta',
        'ip_address',
        'device',
        'user_agent',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'retry_count' => 'integer',
        'meta' => 'array',
        'processed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function isAutomatic(): bool
    {
        return $this->source === 'automatic';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
        ]);
    }

    public function markAsSuccessful(): void
    {
        $this->update([
            'status' => 'success',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function markAsReversed(): void
    {
        $this->update([
            'status' => 'reversed',
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }
}
