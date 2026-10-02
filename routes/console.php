<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The weekly report e-mails: Sunday evening, in the app's timezone (see App\Beszed\Reports).
Schedule::command('beszed:weekly-reports')->weeklyOn(0, '18:00')->timezone(config('beszed.rewards.timezone'));

// A snapshot of the SQLite database every night, kept BACKUP_KEEP_DAYS days (docs/backup.md).
Schedule::command('beszed:backup')->dailyAt('03:30')->timezone(config('beszed.rewards.timezone'))->withoutOverlapping();
