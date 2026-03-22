<?php

use App\Jobs\AutoCancelStaleOrdersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-cancel orders that have been pending/searching_driver for > 30 minutes
Schedule::job(new AutoCancelStaleOrdersJob, 'default')->everyFiveMinutes();
