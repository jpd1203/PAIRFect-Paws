<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Send daily check-in reminders at 08:00.
 * The server cron must have: * * * * * php artisan schedule:run
 */
Schedule::command('checkins:send-reminders')->dailyAt('08:00');

