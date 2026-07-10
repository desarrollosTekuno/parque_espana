<?php

namespace App\Console\Commands;

use App\Models\Classes\ClassSchedule;
use App\Models\Classes\ClassSession;
use Illuminate\Console\Command;

class GenerateClassSessions extends Command
{
    protected $signature = 'classes:generate-sessions
        {--days=7 : Generar sesiones hasta N dias hacia adelante}';

    protected $description = 'Genera las sesiones (fechas concretas) de cada clase para los proximos dias';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $classSchedules = ClassSchedule::all();

        foreach ($classSchedules as $classSchedule) {
            ClassSession::generateForNextDays($classSchedule, $days);
        }

        $this->info("Sesiones generadas para {$classSchedules->count()} clases, {$days} dias hacia adelante.");

        return self::SUCCESS;
    }
}
