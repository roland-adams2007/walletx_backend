<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'environment',
        'public_key',
        'secret_key',
        'status',
        'last_used_at',
    ];

    protected $hidden = [
        'secret_key',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}