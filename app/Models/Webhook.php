<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'url',
        'secret',
        'events',
        'status',
        'payload',
        'delivery_attempts',
        'last_delivery_at',
    ];

    protected $casts = [
        'events' => 'array',
        'payload' => 'array',
        'last_delivery_at' => 'datetime',
        'delivery_attempts' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function incrementDeliveryAttempts(): void
    {
        $this->increment('delivery_attempts');

        $this->update([
            'last_delivery_at' => now(),
        ]);
    }

    public function resetDeliveryAttempts(): void
    {
        $this->update([
            'delivery_attempts' => 0,
        ]);
    }
}
