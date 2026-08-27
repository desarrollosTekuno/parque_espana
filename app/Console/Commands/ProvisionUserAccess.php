<?php

namespace App\Console\Commands;

use App\Models\Memberships\MembershipAccount;
use App\Services\Access\AccessProvisioningService;
use Illuminate\Console\Command;

class ProvisionUserAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:provision-access';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea usuarios y tarjetas de acceso por club a partir de la información ya migrada';

    public function __construct(private AccessProvisioningService $accessService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('');
        $this->info('Iniciando aprovisionamiento de accesos...');
        $this->info('');

        // Obtener las cuentas activas desde memberships.accounts
        $cuentas = MembershipAccount::where('status', 'active')->get();

        $creados = 0;
        $errores = [];

        foreach ($cuentas as $cuenta) {
            // Obtener los integrantes de esta cuenta desde memberships.account_members
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
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════');
        $this->info('  REPORTE DE APROVISIONAMIENTO');
        $this->info('═══════════════════════════════════════════');
        $this->info('');
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
