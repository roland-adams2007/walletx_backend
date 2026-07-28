<?php

namespace App\Services;

use App\Models\Wallet;

class WalletIdGenerator
{
    public static function generate(): string
    {
        do {
            $base = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            $checkDigit = self::luhnCheckDigit($base);
            $walletId = $base . $checkDigit;
        } while (Wallet::where('alt_id', $walletId)->exists());

        return $walletId;
    }

    public static function isValid(string $walletId): bool
    {
        if (!preg_match('/^\d{10}$/', $walletId)) {
            return false;
        }
        $base = substr($walletId, 0, 9);
        $checkDigit = (int) substr($walletId, -1);
        return self::luhnCheckDigit($base) === $checkDigit;
    }

    private static function luhnCheckDigit(string $number): int
    {
        $sum = 0;
        $alt = true;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = !$alt;
        }
        return (10 - ($sum % 10)) % 10;
    }
}
