<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the weekly department reports every Monday morning at 08:00 AM
Schedule::command('department:send-weekly-reports')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->runInBackground();
