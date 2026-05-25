<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

class PruneStaleDeviceTokens extends Command
{
    protected $signature = 'notifications:prune-tokens
        {--days=60 : Eliminar tokens sin actividad por más de N días}';

    protected $description = 'Elimina tokens FCM de dispositivos inactivos (apps desinstaladas, cambios de equipo, etc.)';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $deleted = DeviceToken::stale($days)->delete();

        $this->info("Tokens eliminados: {$deleted}");

        return self::SUCCESS;
    }
}
