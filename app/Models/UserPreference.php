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
        'transaction_receipt_bearer',
        'transaction_fee_bearer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}