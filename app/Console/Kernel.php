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

        $schedule->command('classes:generate-sessions')->daily();

        // Paso 1: Genera las mensualidades del mes (día 1 a las 6:00 AM)
        $schedule->command('memberships:generate-monthly-charges')
            ->monthlyOn(1, '06:00');

        // Paso 2: Cobra domiciliaciones (día 1 a las 7:00 AM, después de generar)
        $schedule->command('billing:process-domiciliated-payments')
            ->monthlyOn(1, '07:00');
    }
}
