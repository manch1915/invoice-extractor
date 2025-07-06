<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('mailbox:poll')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => filled(config('invoices.mailbox.host')));

// The public demo starts every day from the same 41 documents.
Schedule::command('demo:reset')
    ->dailyAt('03:30')
    ->when(fn () => (bool) config('invoices.demo.enabled'));

Schedule::command('horizon:snapshot')->everyFiveMinutes();
