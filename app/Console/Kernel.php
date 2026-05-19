<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registrar comandos
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }

    /**
     * Programación de comandos
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:expire-business-ads')->daily();
    }
}
