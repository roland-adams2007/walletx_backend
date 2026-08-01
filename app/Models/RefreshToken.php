<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $fillable = [
        'session_id',
        'token',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AuthSession::class, 'session_id');
    }
    public function user()
    {
        return $this->session->user;
    }
    public function isValid(): bool
    {
        return is_null($this->revoked_at)
            && $this->expires_at->isFuture();
    }
}
