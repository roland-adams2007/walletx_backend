<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmailToken extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'user_id',
        'type',
        'token_hash',
        'is_used',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public static function generate(string $email, string $type, ?int $userId = null, int $ttlMinutes = 60): array
    {
        $plainToken = Str::random(64);

        $record = static::create([
            'email' => $email,
            'user_id' => $userId,
            'type' => $type,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
        ]);

        return [$plainToken, $record];
    }
    public static function findValid(string $plainToken, string $type): ?self
    {
        return static::query()
            ->where('type', $type)
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    public function markUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => Carbon::now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}