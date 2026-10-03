<?php

namespace App\Console\Commands;

use App\Services\Migration\Dinero\DineroMigrationService;
use Illuminate\Console\Command;

class ImportDinero extends Command
{
    protected $signature = 'migrate:dinero {file : Plantilla_Migracion_Cliente.xlsx} {--dry-run : Validar y revertir los cambios}';

    protected $description = 'Carga los cargos, pagos y aplicaciones históricas de la plantilla del cliente';

    public function handle(DineroMigrationService $service): int
    {
        $file = (string) $this->argument('file');
        if (!is_file($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        try {
            $counts = $service->run($file, (bool) $this->option('dry-run'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($counts as $name => $count) {
            $this->line("{$name}: {$count}");
        }
        $this->info($this->option('dry-run') ? 'Simulación terminada; no se guardaron datos.' : 'Histórico de dinero cargado.');
        return self::SUCCESS;
    }
}
