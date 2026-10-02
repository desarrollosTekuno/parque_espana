<?php

namespace App\Console\Commands;

use App\Services\Migration\Socios\SociosMigrationService;
use Illuminate\Console\Command;

class ImportSocios extends Command
{
    protected $signature = 'migrate:socios {file : Plantilla_Migracion_Cliente.xlsx} {--dry-run : Validar y revertir los cambios} {--sin-personal : Omitir la pestaña Personal}';

    protected $description = 'Carga personal, socios, cuentas, membresías e integrantes desde la plantilla del cliente';

    public function handle(SociosMigrationService $service): int
    {
        $file = (string) $this->argument('file');

        if (!is_file($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        try {
            $counts = $service->run($file, (bool) $this->option('dry-run'), (bool) $this->option('sin-personal'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($counts as $name => $count) {
            $this->line("{$name}: {$count}");
        }

        $this->info($this->option('dry-run') ? 'Simulación terminada; no se guardaron datos.' : 'Carga de socios terminada.');
        return self::SUCCESS;
    }
}
