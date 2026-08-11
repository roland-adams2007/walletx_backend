<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_id',
        'business_id',
        'environment',
        'public_key',
        'secret_key',
        'webhook_url',
        'ip_whitelist',
        'status',
        'last_used_at',
    ];

    protected $hidden = [
        'secret_key',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'ip_whitelist' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isIpWhitelisted(string $ip): bool
    {
        if (empty($this->ip_whitelist)) {
            return true;
        }

        if (is_array($this->ip_whitelist)) {
            return in_array($ip, $this->ip_whitelist);
        }

        if (is_string($this->ip_whitelist)) {
            $whitelist = array_map('trim', explode(',', $this->ip_whitelist));
            return in_array($ip, $whitelist);
        }

        return false;
    }

    public function addIpToWhitelist(string $ip): self
    {
        $whitelist = $this->ip_whitelist ?? [];

        if (!in_array($ip, $whitelist)) {
            $whitelist[] = $ip;
            $this->ip_whitelist = $whitelist;
            $this->save();
        }

        return $this;
    }

    public function addMultipleIpsToWhitelist(array $ips): self
    {
        $whitelist = $this->ip_whitelist ?? [];

        foreach ($ips as $ip) {
            if (!in_array($ip, $whitelist)) {
                $whitelist[] = $ip;
            }
        }

        $this->ip_whitelist = $whitelist;
        $this->save();

        return $this;
    }

    public function removeIpFromWhitelist(string $ip): self
    {
        $whitelist = $this->ip_whitelist ?? [];

        $whitelist = array_filter($whitelist, function ($item) use ($ip) {
            return $item !== $ip;
        });

        $this->ip_whitelist = array_values($whitelist);
        $this->save();

        return $this;
    }

    public function removeMultipleIpsFromWhitelist(array $ips): self
    {
        $whitelist = $this->ip_whitelist ?? [];

        $whitelist = array_filter($whitelist, function ($item) use ($ips) {
            return !in_array($item, $ips);
        });

        $this->ip_whitelist = array_values($whitelist);
        $this->save();

        return $this;
    }

    public function clearIpWhitelist(): self
    {
        $this->ip_whitelist = [];
        $this->save();

        return $this;
    }

    public function getIpWhitelist(): array
    {
        return $this->ip_whitelist ?? [];
    }

    public function hasIpWhitelist(): bool
    {
        return !empty($this->ip_whitelist) && count($this->ip_whitelist) > 0;
    }

    public function scopeWithIpWhitelist($query)
    {
        return $query->whereNotNull('ip_whitelist')
            ->whereRaw('JSON_LENGTH(ip_whitelist) > 0');
    }

    public function scopeWithoutIpWhitelist($query)
    {
        return $query->whereNull('ip_whitelist')
            ->orWhereRaw('JSON_LENGTH(ip_whitelist) = 0');
    }
}
