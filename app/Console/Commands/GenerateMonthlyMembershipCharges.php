<?php

namespace App\Console\Commands;

use App\Models\Memberships\Membership;
use App\Services\Billing\MembershipChargeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class GenerateMonthlyMembershipCharges extends Command
{
    protected $signature = 'memberships:generate-monthly-charges
        {--date= : Fecha del periodo a generar en formato YYYY-MM-DD}
        {--dry-run : Solo muestra los cargos que se generarían sin escribir en base de datos}';

    protected $description = 'Genera las mensualidades del período para membresías activas o suspendidas.';

    protected int $generated = 0;

    protected int $skippedExisting = 0;

    protected int $skippedIneligible = 0;

    protected int $splitGroups = 0;

    public function __construct(
        protected MembershipChargeService $membershipChargeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfMonth()
            : now()->startOfMonth();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Generando mensualidades para %s%s',
            $periodDate->translatedFormat('F Y'),
            $dryRun ? ' (dry-run)' : ''
        ));

        $memberships = Membership::query()
            ->with([
                'club',
                'membershipType',
                'account.primaryHolder.member',
            ])
            ->whereIn('status', ['active', 'suspended'])
            ->where('is_primary', true)
            ->where('monthly_fee', '>', 0)
            ->where(function (Builder $query) use ($periodDate) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $periodDate->copy()->endOfMonth()->toDateString());
            })
            ->where(function (Builder $query) use ($periodDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $periodDate->toDateString());
            })
            ->orderBy('club_id')
            ->orderBy('id')
            ->get();

        $groupedMemberships = $memberships->groupBy(function (Membership $membership) {
            return $membership->account?->account_group_id
                ? 'group:' . $membership->account->account_group_id
                : 'membership:' . $membership->id;
        });

        foreach ($groupedMemberships as $groupMemberships) {
            $eligibleMemberships = $groupMemberships
                ->filter(fn (Membership $membership) => $this->isMembershipEligibleForPeriod($membership, $periodDate))
                ->values();

            if ($eligibleMemberships->isEmpty()) {
                continue;
            }

            if ($this->shouldSplitGroupCharges($eligibleMemberships)) {
                $this->processSplitGroupCharges($eligibleMemberships, $periodDate, $dryRun);
                continue;
            }

            // Un grupo (mismo account_group_id) puede tener más de una
            // membresía cobrable de forma independiente (no relacionadas con
            // el esquema de descuento "ambos parques") — cada una debe
            // generar su propio cargo, no solo la primera que se encuentre.
            $billableMemberships = $eligibleMemberships
                ->filter(fn (Membership $membership) => (bool) $membership->is_billable)
                ->values();

            if ($billableMemberships->isEmpty()) {
                $this->skippedIneligible += $eligibleMemberships->count();
                continue;
            }

            foreach ($billableMemberships as $billableMembership) {
                $this->processSingleMembershipCharge($billableMembership, $periodDate, $dryRun);
            }
        }

        $this->newLine();
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Generadas', $this->generated],
                ['Grupos 50/50', $this->splitGroups],
                ['Omitidas por ya existir', $this->skippedExisting],
                ['Omitidas por no aplicar', $this->skippedIneligible],
            ]
        );

        return self::SUCCESS;
    }

    protected function processSingleMembershipCharge(Membership $membership, Carbon $periodDate, bool $dryRun): void
    {
        if ($this->membershipChargeService->hasMonthlyChargeForPeriod($membership, $periodDate)) {
            $this->skippedExisting++;
            return;
        }

        if (!(bool) $membership->is_billable) {
            $this->skippedIneligible++;
            return;
        }

        $this->line(sprintf(
            'Mensualidad: %s | %s | %s | %s',
            $membership->account?->membership_number ?? 'S/N',
            $membership->club?->code ?? 'N/D',
            $membership->membershipType?->code ?? 'N/D',
            $this->resolveHolderName($membership)
        ));

        if ($dryRun) {
            $this->generated++;
            return;
        }

        $created = $this->membershipChargeService->createRecurringMonthlyCharge(
            membership: $membership,
            periodDate: $periodDate,
            metadata: [
                'charge_origin' => 'monthly_generation',
                'generated_reason' => 'Ciclo mensual automatico',
            ]
        );

        if ($created) {
            $this->generated++;
            return;
        }

        $this->skippedIneligible++;
    }

    protected function processSplitGroupCharges(Collection $memberships, Carbon $periodDate, bool $dryRun): void
    {
        $this->splitGroups++;

        $groupTotalMonthlyFee = $this->resolveSplitGroupTotalMonthlyFee($memberships);
        $groupMembershipCount = $memberships->count();

        if ($groupTotalMonthlyFee <= 0 || $groupMembershipCount === 0) {
            $this->skippedIneligible += $groupMembershipCount;
            return;
        }

        // Delega en el mismo cálculo que usa el alta de una segunda membresía
        // (ver MembershipChargeService::ensureSplitMonthlyChargesForGroup):
        // a cada membresía se le cobra solo lo que le falte para cubrir su
        // mitad del total del grupo, considerando lo que YA tiene cargado
        // ella misma — así una membresía que ya traía cargo del periodo (de
        // antes de tener doble parque, por ejemplo) no hace que la otra
        // absorba el total completo en vez de solo su mitad.
        $results = $this->membershipChargeService->ensureSplitMonthlyChargesForGroup(
            groupMemberships: $memberships,
            groupTotalMonthlyFee: $groupTotalMonthlyFee,
            chargeDate: $periodDate,
            metadata: [
                'charge_origin' => 'monthly_generation',
                'generated_reason' => 'Ciclo mensual automatico 50/50',
            ],
            dryRun: $dryRun
        );

        foreach ($results as $result) {
            /** @var Membership $membership */
            $membership = $result['membership'];

            if (!$result['created']) {
                $this->skippedExisting++;
                continue;
            }

            $this->line(sprintf(
                'Mensualidad 50/50: %s | %s | %s | %s | %s',
                $membership->account?->membership_number ?? 'S/N',
                $membership->club?->code ?? 'N/D',
                $membership->membershipType?->code ?? 'N/D',
                $this->resolveHolderName($membership),
                number_format($result['amount'], 2)
            ));

            $this->generated++;
        }
    }

    protected function shouldSplitGroupCharges(Collection $memberships): bool
    {
        if ($memberships->count() < 2) {
            return false;
        }

        $splitMode = $memberships->pluck('billing_split_mode')->filter()->first() ?? 'single';

        if ($splitMode !== 'equal_split') {
            return false;
        }

        return $memberships
            ->pluck('club_id')
            ->filter()
            ->unique()
            ->count() > 1;
    }

    protected function resolveSplitGroupTotalMonthlyFee(Collection $memberships): float
    {
        $billableMonthlyFee = (float) $memberships
            ->filter(fn (Membership $membership) => (bool) $membership->is_billable)
            ->max(fn (Membership $membership) => $membership->resolved_monthly_fee_total);

        if ($billableMonthlyFee > 0) {
            return $billableMonthlyFee;
        }

        return (float) $memberships->max(fn (Membership $membership) => $membership->resolved_monthly_fee_total);
    }

    protected function isMembershipEligibleForPeriod(Membership $membership, Carbon $periodDate): bool
    {
        $periodEnd = $periodDate->copy()->endOfMonth();

        $startsBeforePeriodEnd = !$membership->start_date || $membership->start_date <= $periodEnd->toDateString();
        $endsAfterPeriodStart = !$membership->end_date || $membership->end_date >= $periodDate->toDateString();

        return $startsBeforePeriodEnd && $endsAfterPeriodStart;
    }

    protected function resolveHolderName(Membership $membership): string
    {
        $holderName = trim(collect([
            $membership->account?->primaryHolder?->member?->first_name,
            $membership->account?->primaryHolder?->member?->last_name,
            $membership->account?->primaryHolder?->member?->second_last_name,
        ])->filter()->implode(' '));

        return $holderName ?: 'Titular sin nombre';
    }
}
