<?php

namespace App\Services\Access;

use App\Models\Memberships\MembershipAccount;
use App\Services\Billing\MembershipChargeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MembershipDelinquencyService
{
    public const OVERDUE_MONTHS_THRESHOLD = 3;
    public const BLOCKED_STATUS = 'blocked';

    public function __construct(private AccessProvisioningService $accessProvisioningService) {}

    /**
     * IDs de todas las MembershipAccount del mismo grupo que la cuenta dada
     * (mismo account_group_id), o solo la propia si no pertenece a ningún
     * grupo. Mismo criterio que CollectionController::resolveGroupAccountIds,
     * pero aquí SIEMPRE se incluye el grupo completo (sin filtrar si
     * "representa combo real") — para morosidad interesa bloquear/desbloquear
     * el acceso físico en TODOS los parques donde el socio tiene cuenta,
     * independientemente de si hay reparto de precio entre ellos.
    */
    public function resolveGroupAccountIds(MembershipAccount $account): array
    {
        if (!$account->account_group_id) {
            return [$account->id];
        }

        return MembershipAccount::query()
            ->where('account_group_id', $account->account_group_id)
            ->pluck('id')
            ->all();
    }

    /**
     * True si la cuenta tiene 3+ MESES CALENDARIO distintos de mensualidad
     * vencida (no cargos — un mismo mes puede tener más de un cargo en
     * escenarios de combo interclub, ver comentarios de
     * MembershipChargeService::resolveMonthlyFeeMonths).
     *
     * Día de gracia: un cargo con due_date = X no cuenta como vencido ni
     * el día X ni el día X+1 (el socio tiene ese día extra de margen).
     * Solo cuenta como vencido a partir de X+2. Por eso se compara contra
     * "hoy menos 1 día" en vez de "hoy":
     *   - due_date = 2026-06-01, hoy = 2026-06-01 → NO vencido (a tiempo)
     *   - due_date = 2026-06-01, hoy = 2026-06-02 → NO vencido (día de gracia)
     *   - due_date = 2026-06-01, hoy = 2026-06-03 → SÍ vencido (aquí empieza a contar)
    */
    public function isAccountDelinquent(MembershipAccount $account): bool
    {
        $groupAccountIds = $this->resolveGroupAccountIds($account);
        $graceCutoff = now()->subDay()->toDateString();

        $overdueMonths = DB::table('billing.charges as c')
            ->join('billing.concepts as concept', 'concept.id', '=', 'c.concept_id')
            ->whereIn('concept.code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)
            ->whereIn('c.membership_account_id', $groupAccountIds)
            ->whereIn('c.status', ['pending', 'partial'])
            ->whereNotNull('c.due_date')
            ->where('c.due_date', '<', $graceCutoff)
            ->selectRaw('COUNT(DISTINCT (c.period_year || \'-\' || c.period_month)) as overdue_months')
            ->value('overdue_months');

        return (int) $overdueMonths >= self::OVERDUE_MONTHS_THRESHOLD;
    }

    /**
     * IDs de cuentas con 3+ meses calendario distintos de mensualidad
     * vencida — usado por el cron para procesar todas de una vez, sin
     * tener que llamar isAccountDelinquent() cuenta por cuenta.
    */
    public function resolveDelinquentAccountIds(): Collection
    {
        $graceCutoff = now()->subDay()->toDateString();

        return DB::table('billing.charges as c')
            ->join('billing.concepts as concept', 'concept.id', '=', 'c.concept_id')
            ->whereIn('concept.code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)
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

    /**
     * Bloquea el acceso de todos los integrantes de la cuenta que aún no
     * estén bloqueados.
    */
    public function blockAccount(MembershipAccount $account): void
    {
        $groupAccountIds = $this->resolveGroupAccountIds($account);
        $accounts = MembershipAccount::whereIn('id', $groupAccountIds)->get();

        foreach ($accounts as $cuenta) {
            $integrantes = $cuenta->accountMembers ?? collect();

            foreach ($integrantes as $integrante) {
                if ($integrante->access_status === self::BLOCKED_STATUS) {
                    continue;
                }

                $integrante->access_status = self::BLOCKED_STATUS;
                $integrante->save();

                $this->accessProvisioningService->updateUserInfo($integrante, $cuenta);
            }
        }
    }


    /**
     * Desbloquea el acceso de todos los integrantes de la cuenta que
     * estén marcados como bloqueados por morosidad.
    */
    public function unblockAccount(MembershipAccount $account): void
    {
        $groupAccountIds = $this->resolveGroupAccountIds($account);
        $accounts = MembershipAccount::whereIn('id', $groupAccountIds)->get();

        foreach ($accounts as $cuenta) {
            $integrantes = $cuenta->accountMembers ?? collect();

            foreach ($integrantes as $integrante) {
                if ($integrante->access_status !== self::BLOCKED_STATUS) {
                    continue;
                }

                $integrante->access_status = 'active';
                $integrante->save();

                $this->accessProvisioningService->updateUserInfo($integrante, $cuenta);
            }
        }
    }

}

