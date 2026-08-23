<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Day-before appointment reminders (run daily at 8:00 AM via cron/scheduler).
Schedule::command('sms:send-reminders')->dailyAt('08:00');
