<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// OPTIONAL (sending works without it). Needs ONE cron entry:  * * * * * cd /path/to/mailbatch && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('mailbatch:recover')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('mailbatch:prune')->dailyAt('03:15');
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:30');
