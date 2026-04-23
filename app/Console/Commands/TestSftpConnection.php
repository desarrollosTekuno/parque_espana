<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestSftpConnection extends Command
{
    protected $signature   = 'sftp:test';
    protected $description = 'Prueba la conexión SFTP y sube un archivo de prueba';

    public function handle(): int
    {
        $this->info('Probando conexión SFTP...');
        $this->newLine();

        // 1. Verificar configuración
        $host = config('filesystems.disks.sftp.host');
        $user = config('filesystems.disks.sftp.username');
        $key  = config('filesystems.disks.sftp.privateKey');
        $root = config('filesystems.disks.sftp.root');

        $this->line("  Host    : <comment>{$host}</comment>");
        $this->line("  Usuario : <comment>{$user}</comment>");
        $this->line("  Llave   : <comment>{$key}</comment>");
        $this->line("  Root    : <comment>{$root}</comment>");
        $this->newLine();

        if (!$host || !$user) {
            $this->error('Faltan variables SFTP en el .env (SFTP_HOST, SFTP_USERNAME).');
            return self::FAILURE;
        }

        if (!file_exists($key)) {
            $this->error("La llave privada no existe en: {$key}");
            return self::FAILURE;
        }

        $this->line('  Llave privada encontrada ✓');

        // 2. Intentar subir un archivo de prueba
        $testPath    = '_test/conexion_prueba_' . now()->format('YmdHis') . '.txt';
        $testContent = "Prueba de conexión SFTP — " . now()->toDateTimeString();

        try {
            $this->line('  Subiendo archivo de prueba...');
            Storage::disk('sftp')->put($testPath, $testContent);
            $this->line("  Subida exitosa ✓  →  {$root}/{$testPath}");
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Error al subir el archivo:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // 3. Verificar que el archivo existe
        try {
            $exists = Storage::disk('sftp')->exists($testPath);
            $this->line('  Verificando existencia  →  ' . ($exists ? '✓ existe' : '✗ no encontrado'));
        } catch (\Throwable $e) {
            $this->warn('No se pudo verificar existencia: ' . $e->getMessage());
        }

        // 4. Leer el contenido de vuelta
        try {
            $read = Storage::disk('sftp')->get($testPath);
            $this->line('  Lectura de vuelta       →  ' . ($read === $testContent ? '✓ contenido correcto' : '✗ contenido diferente'));
        } catch (\Throwable $e) {
            $this->warn('No se pudo leer el archivo: ' . $e->getMessage());
        }

        // 5. Eliminar el archivo de prueba
        try {
            Storage::disk('sftp')->delete($testPath);
            $this->line('  Limpieza del archivo    →  ✓ eliminado');
        } catch (\Throwable $e) {
            $this->warn('No se pudo eliminar el archivo de prueba: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('¡Conexión SFTP funcionando correctamente!');

        return self::SUCCESS;
    }
}
