<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;

class TransactionsExpire extends Command
{
    protected $signature = 'transactions:expire';

    protected $description = 'Mark stale pending transactions as failed';

    const PENDING_TRANSACTION_TIMEOUT_MINUTES = 60;

    public function handle(): int
    {
        $expired = Transaction::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'failed']);

        $timedOut = Transaction::where('status', 'pending')
            ->whereNull('expires_at')
            ->where('created_at', '<', now()->subMinutes(self::PENDING_TRANSACTION_TIMEOUT_MINUTES))
            ->update(['status' => 'failed']);

        $this->info(($expired + $timedOut) . ' transaction(s) marked as failed.');

        return self::SUCCESS;
    }
}
