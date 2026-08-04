<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class Bank extends Model
{
    protected $fillable = [
        'bank_code',
        'name',
        'is_active',
        'slug',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const CACHE_KEY = 'banks.all';
    public const CACHE_TTL = 86400;

    protected static function booted(): void
    {
        static::saved(fn() => Cache::forget(self::CACHE_KEY));
        static::deleted(fn() => Cache::forget(self::CACHE_KEY));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function allCached()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::active()->orderBy('name')->get();
        });
    }
}
