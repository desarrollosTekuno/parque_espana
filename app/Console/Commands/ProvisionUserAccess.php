<?php

namespace App\Console\Commands;

use App\Models\Memberships\MembershipAccount;
use App\Services\Access\AccessProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionUserAccess extends Command
{
    protected $signature = 'users:provision-access
        {--limit= : Limita el número de cuentas a procesar (para pruebas)}
        {--account-id=* : Procesa solo las cuentas con estos IDs específicos (para pruebas)}';

    protected $description = 'Crea usuarios y tarjetas de acceso a partir de las cuentas de membresía activas';

    public function __construct(
        private AccessProvisioningService $accessService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('');
        $this->info('Iniciando aprovisionamiento de accesos...');
        $this->info('');

        // Evita que Laravel acumule el log de cada query en memoria
        DB::connection()->disableQueryLog();

        $query = MembershipAccount::where('status', 'active');

        $accountIds = $this->option('account-id');
        if (!empty($accountIds)) {
            $query->whereIn('id', $accountIds);
            $this->info('Filtrando por account-id: ' . implode(', ', $accountIds));
        }

        $limit = $this->option('limit');
        $procesadas = 0;

        $creados = 0;
        $errores = [];

        $query->chunkById(100, function ($cuentas) use (&$creados, &$errores, &$procesadas, $limit) {
            foreach ($cuentas as $cuenta) {
                if ($limit && $procesadas >= $limit) {
                    return false; // corta el chunk() si ya llegamos al límite
                }

                $integrantes = $cuenta->accountMembers ?? collect();

                foreach ($integrantes as $integrante) {
                    try {
                        $this->accessService->provision($integrante, $cuenta);
                        $creados++;
                    } catch (\Throwable $e) {
                        $errores[] = [
                            'cuenta_id' => $cuenta->id ?? null,
                            'member_id' => $integrante->member_id ?? null,
                            'message' => $e->getMessage(),
                        ];
                    }
                }

                $procesadas++;
            }
        });

        $this->info('');
        $this->info('═══════════════════════════════════════════');
        $this->info('  REPORTE DE APROVISIONAMIENTO');
        $this->info('═══════════════════════════════════════════');
        $this->info('');
        $this->info("  Cuentas procesadas: {$procesadas}");
        $this->info("  Registros creados: {$creados}");
        $this->info("  Errores: " . count($errores));
        $this->info('');

        foreach ($errores as $error) {
            $this->warn("  ↳ Cuenta {$error['cuenta_id']} / Miembro {$error['member_id']}: {$error['message']}");
        }

        $this->info('');

        return empty($errores) ? self::SUCCESS : self::FAILURE;
    }
}
