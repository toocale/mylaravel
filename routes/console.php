<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Maintenance reminders - runs daily at 7 AM
Schedule::command('maintenance:send-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/maintenance-reminders.log'));

// Andon alert rules check - runs every 2 minutes
// Schedule::job(new \App\Jobs\CheckAlertRulesJob)->everyTwoMinutes()
//    ->withoutOverlapping()
//    ->appendOutputTo(storage_path('logs/andon-alerts.log'));
