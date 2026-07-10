<?php

namespace App\Console\Commands;

use App\Services\ClassSessionGeneratorService;
use Illuminate\Console\Command;

class GenerateClassSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:generate-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera las sesiones faltantes de los horarios de clases activos';

    /**
     * Execute the console command.
     */
    public function handle(ClassSessionGeneratorService $generator)
    {
        $count = $generator->generateAll();

        $this->info("Sesiones generadas para {$count} horarios activos.");

        return 0;
    }
}
