<?php

use App\Jobs\AutoCancelStaleOrdersJob;
use App\Jobs\ExpireDriverTokensJob;
use App\Jobs\GenerateDailyReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-cancel orders pending/searching_driver for > 30 minutes
Schedule::job(new AutoCancelStaleOrdersJob, 'default')->everyFiveMinutes();

// Revoke tokens for suspended drivers — runs nightly at 02:00
Schedule::job(new ExpireDriverTokensJob, 'default')->dailyAt('02:00');

// Generate yesterday's daily report — runs at 00:05 every day
Schedule::job(new GenerateDailyReportJob, 'default')->dailyAt('00:05');

// Monitor failed jobs every 15 minutes — alerts if > 5 failures in last hour
Schedule::command('queue:monitor-failed --threshold=5')->everyFifteenMinutes();
