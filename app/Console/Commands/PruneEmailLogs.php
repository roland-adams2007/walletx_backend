<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailLog;

class PruneEmailLogs extends Command
{
    protected $signature = 'emails:prune
        {--sent-days=30 : Delete sent emails older than this}
        {--failed-days=90 : Delete failed emails older than this}';

    protected $description = 'Prune email logs using retention rules for sent and failed emails';

    public function handle(): int
    {
        $sentDays   = (int) $this->option('sent-days');
        $failedDays = (int) $this->option('failed-days');

        $sentCutoff   = now()->subDays($sentDays);
        $failedCutoff = now()->subDays($failedDays);

        $this->info("Pruning email logs...");
        $this->info("Sent older than {$sentDays} days (before {$sentCutoff})");
        $this->info("Failed older than {$failedDays} days (before {$failedCutoff})");

        // 1. Delete sent logs
        $sentDeleted = EmailLog::whereNotNull('sent_at')
            ->where('sent_at', '<', $sentCutoff)
            ->delete();

        // 2. Delete failed logs
        $failedDeleted = EmailLog::whereNotNull('failed_at')
            ->where('failed_at', '<', $failedCutoff)
            ->delete();

        $this->info("Done.");
        $this->info("Sent deleted: {$sentDeleted}");
        $this->info("Failed deleted: {$failedDeleted}");

        return self::SUCCESS;
    }
}