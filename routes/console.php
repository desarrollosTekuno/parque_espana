<?php

// use App\Console\Commands\DispatchScheduledNotifications;
use App\Console\Commands\GenerateMonthlyMembershipCharges;
use App\Console\Commands\ProcessMembershipAgeTransitions;
use App\Console\Commands\SendScheduledNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command(DispatchScheduledNotifications::class)->everyMinute();
Schedule::command(GenerateMonthlyMembershipCharges::class)->monthlyOn(1, '01:00');
Schedule::command(ProcessMembershipAgeTransitions::class)->dailyAt('01:00');
Schedule::command(SendScheduledNotifications::class)->everyMinute();
