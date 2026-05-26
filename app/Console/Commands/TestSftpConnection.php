<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestSftpConnection extends Command
{
    protected $signature   = 'spaces:test';
    protected $description = 'Prueba la conexión con DigitalOcean Spaces y sube un archivo de prueba';

    public function handle(): int
    {
        $this->info('Probando conexión con DigitalOcean Spaces...');
        $this->newLine();

        $key      = config('filesystems.disks.spaces.key');
        $bucket   = config('filesystems.disks.spaces.bucket');
        $region   = config('filesystems.disks.spaces.region');
        $endpoint = config('filesystems.disks.spaces.endpoint');

        $this->line("  Bucket   : <comment>{$bucket}</comment>");
        $this->line("  Región   : <comment>{$region}</comment>");
        $this->line("  Endpoint : <comment>{$endpoint}</comment>");
        $this->newLine();

        if (!$key || !$bucket) {
            $this->error('Faltan variables en el .env (DO_SPACES_KEY, DO_SPACES_BUCKET).');
            return self::FAILURE;
        }

        $testPath    = '_test/conexion_prueba_' . now()->format('YmdHis') . '.txt';
        $testContent = "Prueba de conexión DigitalOcean Spaces — " . now()->toDateTimeString();

        try {
            $this->line('  Subiendo archivo de prueba...');
            Storage::disk('spaces')->put($testPath, $testContent);
            $this->line("  Subida exitosa ✓  →  {$testPath}");
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Error al subir el archivo:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        try {
            $exists = Storage::disk('spaces')->exists($testPath);
            $this->line('  Verificando existencia  →  ' . ($exists ? '✓ existe' : '✗ no encontrado'));
        } catch (\Throwable $e) {
            $this->warn('No se pudo verificar existencia: ' . $e->getMessage());
        }

        try {
            $read = Storage::disk('spaces')->get($testPath);
            $this->line('  Lectura de vuelta       →  ' . ($read === $testContent ? '✓ contenido correcto' : '✗ contenido diferente'));
        } catch (\Throwable $e) {
            $this->warn('No se pudo leer el archivo: ' . $e->getMessage());
        }

        // try {
        //     Storage::disk('spaces')->delete($testPath);
        //     $this->line('  Limpieza del archivo    →  ✓ eliminado');
        // } catch (\Throwable $e) {
        //     $this->warn('No se pudo eliminar el archivo de prueba: ' . $e->getMessage());
        // }

        $this->newLine();
        $this->info('¡Conexión con DigitalOcean Spaces funcionando correctamente!');

        return self::SUCCESS;
    }
}
