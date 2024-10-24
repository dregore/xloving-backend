<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:test-job')->everyFifteenMinutes();
Schedule::command('app:check-pending')->hourly();
Schedule::command('app:delayed-job')->everyMinute();
