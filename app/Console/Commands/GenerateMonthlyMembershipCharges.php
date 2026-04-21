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

    protected $description = 'Genera las mensualidades del periodo para membresías activas o suspendidas.';

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

            $singleMembership = $eligibleMemberships
                ->first(fn (Membership $membership) => (bool) $membership->is_billable)
                ?? $eligibleMemberships->first();

            if (!$singleMembership) {
                $this->skippedIneligible++;
                continue;
            }

            $this->processSingleMembershipCharge($singleMembership, $periodDate, $dryRun);
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

        $splitAmount = round($groupTotalMonthlyFee / $groupMembershipCount, 2);

        /** @var Membership|null $lastMembership */
        $lastMembership = $memberships->last();
        $allocated = 0.0;

        foreach ($memberships as $membership) {
            if ($this->membershipChargeService->hasMonthlyChargeForPeriod($membership, $periodDate)) {
                $this->skippedExisting++;
                continue;
            }

            $membershipAmount = $membership->is($lastMembership)
                ? round($groupTotalMonthlyFee - $allocated, 2)
                : $splitAmount;

            if ($membershipAmount <= 0) {
                $this->skippedIneligible++;
                continue;
            }

            $allocated = round($allocated + $membershipAmount, 2);

            $this->line(sprintf(
                'Mensualidad 50/50: %s | %s | %s | %s | %s',
                $membership->account?->membership_number ?? 'S/N',
                $membership->club?->code ?? 'N/D',
                $membership->membershipType?->code ?? 'N/D',
                $this->resolveHolderName($membership),
                number_format($membershipAmount, 2)
            ));

            if ($dryRun) {
                $this->generated++;
                continue;
            }

            $created = $this->membershipChargeService->createRecurringMonthlyCharge(
                membership: $membership,
                periodDate: $periodDate,
                metadata: [
                    'charge_origin' => 'monthly_generation',
                    'generated_reason' => 'Ciclo mensual automatico 50/50',
                    'split_mode' => 'equal_group_split',
                    'split_group_total' => $groupTotalMonthlyFee,
                    'split_group_memberships' => $groupMembershipCount,
                ],
                monthlyFeeOverride: $membershipAmount,
                ignoreBillableState: true
            );

            if ($created) {
                $this->generated++;
                continue;
            }

            $this->skippedIneligible++;
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
