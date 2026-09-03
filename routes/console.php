<?php

// use App\Console\Commands\DispatchScheduledNotifications;

use App\Console\Commands\ExpireDailyPassCards;
use App\Console\Commands\GenerateMonthlyMembershipCharges;
use App\Console\Commands\ProcessMembershipAgeTransitions;
use App\Console\Commands\ProcessMembershipDelinquency;
use App\Console\Commands\PruneStaleDeviceTokens;
use App\Console\Commands\SendScheduledEmailNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command(DispatchScheduledNotifications::class)->everyMinute();
Schedule::command(GenerateMonthlyMembershipCharges::class)->monthlyOn(1, '01:00');
// Schedule::command(ProcessMembershipAgeTransitions::class)->dailyAt('01:00');
Schedule::command(ProcessMembershipAgeTransitions::class)->everyMinute();
Schedule::command(PruneStaleDeviceTokens::class)->weekly();
Schedule::command(SendScheduledEmailNotifications::class)->everyMinute();
Schedule::command(ExpireDailyPassCards::class)->everyFifteenMinutes();
Schedule::command(ProcessMembershipDelinquency::class)->dailyAt('02:00');
