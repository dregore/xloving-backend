<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:check-frozen')->everyFifteenMinutes();
Schedule::command('app:check-running')->everyFifteenMinutes();
Schedule::command('app:check-pending')->cron('10 * * * *');
Schedule::command('app:check-delayed')->everyFifteenMinutes();
Schedule::command('telescope:prune')->daily();
// Schedule::command('app:check-proxy')->everyTenMinutes();
