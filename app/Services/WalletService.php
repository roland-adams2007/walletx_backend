<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Create a wallet for a user.
     */
    public function createWallet(User $user): Wallet
    {
        return DB::transaction(function () use ($user) {
            if ($user->wallet) {
                return $user->wallet;
            }
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'alt_id' =>  WalletIdGenerator::generate(),
                'balance' =>  0,
                'pending_balance' =>  0,
                'is_active' =>  true,
            ]);

            return $wallet;
        });
    }

    /**
     * Get user's wallet with balance in base currency.
     */
    public function getWallet(User $user): ?array
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            $wallet = $this->createWallet($user);
        }

        return [
            'alt_id' => $wallet->alt_id,
            'balance' => $wallet->balance / 100,
            'pending_balance' => $wallet->pending_balance / 100,
            'is_active' => $wallet->is_active,
            'last_transaction_at' => $wallet->last_transaction_at,
        ];
    }

    /**
     * Credit a user's wallet.
     */
    public function creditWallet(User $user, int $amountInKobo, string $description = null, array $meta = []): WalletTransaction
    {
        if (!$user->wallet) {
            throw new \Exception('User does not have a wallet');
        }

        return DB::transaction(function () use ($user, $amountInKobo, $description, $meta) {
            $wallet = $user->wallet;

            // Create transaction
            $transaction = WalletTransaction::create([
                'alt_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amountInKobo,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance + $amountInKobo,
                'reference' => 'REF-' . strtoupper(Str::random(12)),
                'description' => $description,
                'meta' => $meta,
                'status' => 'completed',
            ]);

            // Update wallet
            $wallet->credit($amountInKobo);
            $wallet->update(['last_transaction_at' => now()]);

            return $transaction;
        });
    }

    /**
     * Debit a user's wallet.
     */
    public function debitWallet(User $user, int $amountInKobo, string $description = null, array $meta = []): WalletTransaction
    {
        if (!$user->wallet) {
            throw new \Exception('User does not have a wallet');
        }

        if (!$user->hasSufficientBalance($amountInKobo)) {
            throw new \Exception('Insufficient balance');
        }

        return DB::transaction(function () use ($user, $amountInKobo, $description, $meta) {
            $wallet = $user->wallet;

            // Create transaction
            $transaction = WalletTransaction::create([
                'alt_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amountInKobo,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance - $amountInKobo,
                'reference' => 'REF-' . strtoupper(Str::random(12)),
                'description' => $description,
                'meta' => $meta,
                'status' => 'completed',
            ]);

            // Update wallet
            $wallet->debit($amountInKobo);
            $wallet->update(['last_transaction_at' => now()]);

            return $transaction;
        });
    }

    /**
     * Transfer between two users.
     */
    public function transfer(User $sender, User $recipient, int $amountInKobo, string $description = null, array $meta = []): array
    {
        return DB::transaction(function () use ($sender, $recipient, $amountInKobo, $description, $meta) {
            // Check if sender has enough balance
            if (!$sender->hasSufficientBalance($amountInKobo)) {
                throw new \Exception('Insufficient balance');
            }

            // Check if recipient has a wallet
            if (!$recipient->wallet) {
                throw new \Exception('Recipient does not have a wallet');
            }

            // Debit sender
            $senderTransaction = WalletTransaction::create([
                'alt_id' => $sender->wallet->id,
                'counterparty_alt_id' => $recipient->wallet->id,
                'user_id' => $sender->id,
                'type' => 'debit',
                'amount' => $amountInKobo,
                'balance_before' => $sender->wallet->balance,
                'balance_after' => $sender->wallet->balance - $amountInKobo,
                'reference' => 'TRF-' . strtoupper(Str::random(12)),
                'description' => $description,
                'meta' => array_merge($meta, ['recipient_id' => $recipient->id]),
                'status' => 'completed',
            ]);

            $sender->wallet->debit($amountInKobo);
            $sender->wallet->update(['last_transaction_at' => now()]);

            // Credit recipient
            $recipientTransaction = WalletTransaction::create([
                'alt_id' => $recipient->wallet->id,
                'counterparty_alt_id' => $sender->wallet->id,
                'user_id' => $recipient->id,
                'type' => 'credit',
                'amount' => $amountInKobo,
                'balance_before' => $recipient->wallet->balance,
                'balance_after' => $recipient->wallet->balance + $amountInKobo,
                'reference' => 'TRF-' . strtoupper(Str::random(12)),
                'description' => $description,
                'meta' => array_merge($meta, ['sender_id' => $sender->id]),
                'status' => 'completed',
            ]);

            $recipient->wallet->credit($amountInKobo);
            $recipient->wallet->update(['last_transaction_at' => now()]);

            return [
                'sender_transaction' => $senderTransaction,
                'recipient_transaction' => $recipientTransaction,
            ];
        });
    }

    /**
     * Get transaction history for a user.
     */
    public function getTransactionHistory(User $user, int $limit = 50, int $offset = 0): array
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            return ['data' => [], 'total' => 0];
        }

        $transactions = WalletTransaction::where('alt_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = WalletTransaction::where('alt_id', $wallet->id)->count();

        return [
            'data' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount / 100,
                    'amount_in_kobo' => $transaction->amount,
                    'balance_before' => $transaction->balance_before / 100,
                    'balance_after' => $transaction->balance_after / 100,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'meta' => $transaction->meta,
                    'created_at' => $transaction->created_at,
                ];
            }),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get wallet summary.
     */
    public function getWalletSummary(User $user): array
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            return [
                'has_wallet' => false,
                'balance' => 0,
                'pending_balance' => 0,
                'total_credited' => 0,
                'total_debited' => 0,
                'transaction_count' => 0,
            ];
        }

        $totalCredited = WalletTransaction::where('alt_id', $wallet->id)
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount');

        $totalDebited = WalletTransaction::where('alt_id', $wallet->id)
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount');

        $transactionCount = WalletTransaction::where('alt_id', $wallet->id)->count();

        return [
            'has_wallet' => true,
            'alt_id' => $wallet->id,
            'alt_id' => $wallet->alt_id,
            'balance' => $wallet->balance / 100,
            'balance_in_kobo' => $wallet->balance,
            'pending_balance' => $wallet->pending_balance / 100,
            'is_active' => $wallet->is_active,
            'total_credited' => $totalCredited / 100,
            'total_debited' => $totalDebited / 100,
            'transaction_count' => $transactionCount,
            'last_transaction_at' => $wallet->last_transaction_at,
            'created_at' => $wallet->created_at,
        ];
    }

    /**
     * Deactivate a wallet.
     */
    public function deactivateWallet(User $user): bool
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            throw new \Exception('User does not have a wallet');
        }

        $wallet->update(['is_active' => false]);
        return true;
    }

    /**
     * Activate a wallet.
     */
    public function activateWallet(User $user): bool
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            throw new \Exception('User does not have a wallet');
        }

        $wallet->update(['is_active' => true]);
        return true;
    }

    /**
     * Get pending balance (transactions pending approval).
     */
    public function getPendingBalance(User $user): float
    {
        $wallet = $user->wallet;
        return $wallet?->pending_balance / 100 ?? 0;
    }

    /**
     * Add to pending balance.
     */
    public function addPendingBalance(User $user, int $amountInKobo): void
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            throw new \Exception('User does not have a wallet');
        }

        $wallet->pending_balance += $amountInKobo;
        $wallet->save();
    }

    /**
     * Clear pending balance.
     */
    public function clearPendingBalance(User $user): void
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            throw new \Exception('User does not have a wallet');
        }

        $wallet->pending_balance = 0;
        $wallet->save();
    }
}
