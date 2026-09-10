<?php

namespace App\Console\Commands;

use App\Models\Memberships\MembershipAccount;
use App\Services\Access\AccessProvisioningService;
use App\Services\Access\MembershipDelinquencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessMembershipDelinquency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:process-delinquency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bloquea el acceso de las cuentas con 3 o más meses de mensualidad vencida';

    public function __construct(
        private MembershipDelinquencyService $delinquencyService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('');
        $this->info('Buscando cuentas con mensualidad vencida...');
        $this->info('');

        $accountIds = $this->delinquencyService->resolveDelinquentAccountIds();

        $this->info("Cuentas con " . MembershipDelinquencyService::OVERDUE_MONTHS_THRESHOLD . "+ meses vencidos: {$accountIds->count()}");
        $this->info('');

        $bloqueados = 0;
        $errores = [];

        $cuentas = MembershipAccount::whereIn('id', $accountIds)->get();

        foreach ($cuentas as $cuenta) {
            try {
                $this->delinquencyService->blockAccount($cuenta);
                $bloqueados++;
            } catch (\Throwable $e) {
                $errores[] = [
                    'cuenta_id' => $cuenta->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════');
        $this->info('  REPORTE DE BLOQUEO POR MOROSIDAD');
        $this->info('═══════════════════════════════════════════');
        $this->info('');
        $this->info("  Cuentas procesadas: {$bloqueados}");
        $this->info("  Errores: " . count($errores));
        $this->info('');

        foreach ($errores as $error) {
            $this->warn("  ↳ Cuenta {$error['cuenta_id']}: {$error['message']}");
        }

        $this->info('');

        return empty($errores) ? self::SUCCESS : self::FAILURE;
    }

}
