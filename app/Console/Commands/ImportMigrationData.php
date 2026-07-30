<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationOrchestrator;
use Illuminate\Console\Command;

class ImportMigrationData extends Command
{
    /* 
    Ejemplo de uso:
    php artisan migrate:data "C:\Users\Usuario\Downloads\Plantilla_Migracion_DatosDP_230626.xlsx" --only=usuarios,membresias,integrantes,domicilios,empleo,"historial por periodo",casilleros --dry-run
    */
    protected $signature = 'migrate:data
        {file : Ruta al archivo .xlsx de migración}
        {--dry-run : Simula la importación sin guardar nada en base de datos}
        {--only= : Importadores a ejecutar separados por coma (ej: usuarios,membresias)}';

    protected $description = 'Importa datos históricos desde la plantilla Excel de migración';

    public function handle(MigrationOrchestrator $orchestrator): int
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');
        $only = $this->option('only')
            ? array_map('trim', explode(',', $this->option('only')))
            : [];

        // Validar que el archivo exista
        if (!file_exists($filePath)) {
            $this->error("Archivo no encontrado: {$filePath}");
            return self::FAILURE;
        }

        $this->info('');
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     IMPORTACIÓN DE DATOS DE MIGRACIÓN    ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('  Modo DRY-RUN activado — no se guardará nada en base de datos.');
            $this->info('');
        }

        if (!empty($only)) {
            $this->info('  Importadores seleccionados: ' . implode(', ', $only));
            $this->info('');
        }

        $this->info("  Archivo: {$filePath}");
        $this->info('');

        if (!$this->confirm('¿Desea continuar?', true)) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('Procesando...');
        $this->info('');

        try {
            $report = $orchestrator->run($filePath, $dryRun, $only);
        } catch (\Throwable $e) {
            $this->error('Error crítico durante la importación:');
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        // Mostrar reporte
        $this->info('═══════════════════════════════════════════');
        $this->info('  REPORTE DE IMPORTACIÓN');
        $this->info('═══════════════════════════════════════════');
        $this->info('');

        foreach ($report->toArray() as $importerName => $result) {
            $icon = empty($result['errors']) ? '✅' : '⚠ ';
            $status = sprintf('%-30s  %d importados  |  %d errores', $importerName, $result['ok'], count($result['errors']));
            $this->line("  {$icon}  {$status}");

            foreach ($result['errors'] as $error) {
                $rowLabel = $error['row'] > 0 ? "Fila {$error['row']}" : 'General';
                $this->warn("       ↳ [{$rowLabel}] {$error['message']}");
            }
        }

        $this->info('');

        if ($report->hasErrors()) {
            $this->warn('  Importación completada con errores. Revisa los detalles arriba.');
        } else {
            $this->info('  Importación completada exitosamente.');
        }

        if ($dryRun) {
            $this->info('');
            $this->warn('  Recuerda: el modo DRY-RUN no guardó ningún cambio en base de datos.');
        }

        $this->info('');

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
