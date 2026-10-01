<?php

use App\Models\PostAdoptionCaptureChallenge;
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
Schedule::command('checkins:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
Schedule::command('reservations:process-timeouts')->hourly()->withoutOverlapping();
Schedule::command('matching:recompute')->hourly()->withoutOverlapping();

Schedule::call(function (): void {
    PostAdoptionCaptureChallenge::query()
        ->whereNull('consumed_at')
        ->where('expires_at', '<', now()->subDay())
        ->delete();
})->name('post-adoption:prune-capture-challenges')
    ->dailyAt('03:30')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
