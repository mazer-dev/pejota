<?php

use App\Console\Commands\GenerateRecurringTasks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(GenerateRecurringTasks::class)->dailyAt('00:30');
Schedule::command('exchange-rate:fetch')->dailyAt('06:00');
Schedule::command('pj:whatsapp-sync-recent')->dailyAt('06:00');
Schedule::command('pj:daily-plan')->dailyAt('07:00');
Schedule::command('pj:daily-plan:send')->dailyAt('08:00');
