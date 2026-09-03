<?php

namespace App\Console\Commands;

use App\Models\Memberships\MembershipAccount;
use App\Services\Access\AccessProvisioningService;
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

    // Meses de calendario distintos de mensualidad vencida para bloquear el acceso.
    private const OVERDUE_MONTHS_THRESHOLD = 3;

    // Valor de access_status que se asigna a un integrante bloqueado por morosidad. 
    private const BLOCKED_STATUS = 'blocked';

    public function __construct(
        private AccessProvisioningService $accessProvisioningService
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

        $accountIds = $this->resolveDelinquentAccountIds();

        $this->info("Cuentas con " . self::OVERDUE_MONTHS_THRESHOLD . "+ meses vencidos: {$accountIds->count()}");
        $this->info('');

        $bloqueados = 0;
        $errores = [];

        $cuentas = MembershipAccount::whereIn('id', $accountIds)->get();

        foreach ($cuentas as $cuenta) {
            $integrantes = $cuenta->accountMembers ?? collect();

            foreach ($integrantes as $integrante) {
                try {
                    // Si ya está bloqueado, no se vuelve a procesar en cada corrida del cron 
                    if ($integrante->access_status === self::BLOCKED_STATUS) {
                        continue;
                    }

                    $integrante->access_status = self::BLOCKED_STATUS;
                    $integrante->save();

                    // updateUserInfo() lee access_status y arma el
                    // update_user con is_active=false hacia todos los
                    // dispositivos activos del club de la cuenta.
                    $this->accessProvisioningService->updateUserInfo($integrante, $cuenta);
                    $bloqueados++;
                } catch (\Throwable $e) {
                    $errores[] = [
                        'cuenta_id' => $cuenta->id,
                        'member_id' => $integrante->member_id ?? null,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $this->info('');
            $this->info('═══════════════════════════════════════════');
            $this->info('  REPORTE DE BLOQUEO POR MOROSIDAD');
            $this->info('═══════════════════════════════════════════');
            $this->info('');
            $this->info("  Integrantes bloqueados: {$bloqueados}");
            $this->info("  Errores: " . count($errores));
            $this->info('');

            foreach ($errores as $error) {
                $this->warn("  ↳ Cuenta {$error['cuenta_id']} / Miembro {$error['member_id']}: {$error['message']}");
            }

            $this->info('');

            return empty($errores) ? self::SUCCESS : self::FAILURE;
        }
    }

    /**
     * IDs de cuentas con 3+ MESES CALENDARIO distintos de mensualidad
     * vencida (no cargos — un mismo mes puede tener más de un cargo en
     * escenarios de combo interclub).
     *
     * Día de gracia: un cargo con due_date = X no cuenta como vencido ni
     * el día X ni el día X+1 (el socio tiene ese día extra de margen).
     * Solo cuenta como vencido a partir de X+2. Por eso se compara contra
     * "hoy menos 1 día" en vez de "hoy":
     *   - due_date = 2026-06-01, hoy = 2026-06-01 → NO vencido (a tiempo)
     *   - due_date = 2026-06-01, hoy = 2026-06-02 → NO vencido (día de gracia)
     *   - due_date = 2026-06-01, hoy = 2026-06-03 → SÍ vencido (aquí empieza a contar)
     */
    private function resolveDelinquentAccountIds(): Collection
    {
        $graceCutoff = now()->subDay()->toDateString();

        return DB::table('billing.charges as c')
            ->join('billing.concepts as concept', 'concept.id', '=', 'c.concept_id')
            ->where('concept.code', 'MONTHLY_FEE')
            ->whereIn('c.status', ['pending', 'partial'])
            ->whereNotNull('c.due_date')
            ->where('c.due_date', '<', $graceCutoff)
            ->select('c.membership_account_id')
            ->selectRaw('COUNT(DISTINCT (c.period_year || \'-\' || c.period_month)) as overdue_months')
            ->groupBy('c.membership_account_id')
            ->havingRaw('COUNT(DISTINCT (c.period_year || \'-\' || c.period_month)) >= ?', [self::OVERDUE_MONTHS_THRESHOLD])
            ->get()
            ->pluck('membership_account_id');
    }
}
