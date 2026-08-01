<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthSession extends Model
{
    protected $fillable = [
        'user_id',
        'access_token_id',
        'device_id',
        'device_name',
        'device_type',
        'platform',
        'browser',
        'ip_address',
        'user_agent',
        'last_activity',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'expires_at'    => 'datetime',
        'revoked_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'session_id');
    }

    public function activeRefreshToken()
    {
        return $this->hasOne(RefreshToken::class, 'session_id')
            ->whereNull('revoked_at')
            ->latest();
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at)
            && $this->expires_at->isFuture();
    }
}
