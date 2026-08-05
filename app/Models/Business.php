<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'alt_id',
        'user_id',
        'name',
        'email',
        'phone',
        'business_type',
        'industry',
        'logo',
        'balance',
        'pending_balance',
        'max_balance',
        'kyc_status',
        'kyc_verified_at',
        'settlement_bank_code',
        'settlement_account_number',
        'settlement_account_name',
        'is_active',
        'last_transaction_at',
    ];

    protected $casts = [
        'balance' => 'integer',
        'pending_balance' => 'integer',
        'max_balance' => 'integer',
        'is_active' => 'boolean',
        'kyc_verified_at' => 'datetime',
        'last_transaction_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business) {
            if (empty($business->alt_id)) {
                do {
                    $length = random_int(6, 8);
                    $altId = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);
                } while (static::where('alt_id', $altId)->exists());

                $business->alt_id = $altId;
            }
        });

        static::created(function (Business $business) {
            $business->preference()->create([
                'send_receipt_to_business' => true,
                'send_receipt_to_customer' => false,
                'charge_fee_to_customer' => false,
            ]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'alt_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function logoUpload(): BelongsTo
    {
        return $this->belongsTo(Uploads::class, 'logo');
    }

    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    public function getLogoUrlAttribute(): string
    {
        $upload = $this->logoUpload;

        if ($upload) {
            return $upload->file_name;
        }

        return url('/logo.png');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
