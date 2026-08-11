<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('transactions:expire')->everyFifteenMinutes();
Schedule::command('payouts:settle')->dailyAt('14:00');
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::command('emails:prune --sent-days=30 --failed-days=90')
    ->dailyAt('02:30')
    ->withoutOverlapping();
