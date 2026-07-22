<?php

namespace App\Services\Migration;

class MigrationReport
{
    private array $results = [];

    public function record(string $importer, int $ok, array $errors): void
    {
        $this->results[$importer] = [
            'ok'     => $ok,
            'errors' => $errors,
        ];
    }

    public function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if (!empty($result['errors'])) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return $this->results;
    }

    /**
     * Retorna un resumen legible para la consola.
     */
    public function summary(): string
    {
        $lines = [];

        foreach ($this->results as $importer => $result) {
            $ok     = $result['ok'];
            $errors = count($result['errors']);
            $icon   = $errors > 0 ? '⚠' : '✅';

            $lines[] = sprintf('%s %-30s %d importados, %d errores', $icon, $importer . ':', $ok, $errors);

            foreach ($result['errors'] as $error) {
                $lines[] = sprintf('   ↳ Fila %d: %s', $error['row'], $error['message']);
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
