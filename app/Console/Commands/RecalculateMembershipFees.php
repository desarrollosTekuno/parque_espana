<?php

namespace App\Console\Commands;

use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipType;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\MembershipPricingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RecalculateMembershipFees extends Command
{
    protected $signature = 'memberships:recalculate-fees
        {--type=   : Código del tipo de membresía (ej. FAM_PE). Si se omite, aplica a todos.}
        {--club=   : Código del club (ej. PE). Si se omite, aplica a todos los clubs.}
        {--dry-run : Muestra los cambios que se aplicarían sin escribir en base de datos.}';

    protected $description = 'Recalcula las cuotas mensuales de las membresías activas según las reglas de precio vigentes.';

    protected int $updated   = 0;
    protected int $unchanged = 0;
    protected int $skipped   = 0;

    public function __construct(
        protected MembershipPricingService $pricingService,
        protected MembershipChargeService  $chargeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $typeCode = $this->option('type') ? strtoupper(trim((string) $this->option('type'))) : null;
        $clubCode = $this->option('club') ? strtoupper(trim((string) $this->option('club'))) : null;

        $this->info(sprintf(
            'Recalculando cuotas%s%s%s',
            $typeCode ? " · tipo={$typeCode}" : '',
            $clubCode ? " · club={$clubCode}" : '',
            $dryRun   ? ' (dry-run)'           : ''
        ));

        $memberships = Membership::query()
            ->with([
                'membershipType',
                'club',
                'account.primaryHolder.member',
                'account.accountGroup',
            ])
            ->whereIn('status', ['active', 'suspended'])
            ->where('is_primary', true)
            ->when($typeCode, fn (Builder $q) => $q->whereHas(
                'membershipType',
                fn (Builder $q) => $q->where('code', $typeCode)
            ))
            ->when($clubCode, fn (Builder $q) => $q->whereHas(
                'club',
                fn (Builder $q) => $q->where('code', $clubCode)
            ))
            // Dentro de un mismo grupo interclub, la membresía facturable debe
            // procesarse primero: es la que realmente genera cargos y de la que
            // debe tomarse la regla de precio vigente. Si la no facturable
            // "reclamara" el grupo primero (por orden de club_id/id), la
            // facturable quedaría saltada por el control de deduplicación de
            // abajo y nunca se recalcularía.
            ->orderByDesc('is_billable')
            ->orderBy('club_id')
            ->orderBy('id')
            ->get();

        if ($memberships->isEmpty()) {
            $this->warn('No se encontraron membresías con los filtros indicados.');
            return self::SUCCESS;
        }

        // Agrupar para no procesar dos veces membresías del mismo grupo interclub
        $processedGroups = [];

        foreach ($memberships as $membership) {
            $groupId = $membership->account?->account_group_id;

            if ($groupId && in_array($groupId, $processedGroups, true)) {
                continue;
            }

            if ($groupId) {
                $processedGroups[] = $groupId;
            }

            $this->processMembership($membership, $dryRun);
        }

        $this->newLine();
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Actualizadas',          $this->updated],
                ['Sin cambio',            $this->unchanged],
                ['Sin regla (omitidas)',  $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    protected function processMembership(Membership $membership, bool $dryRun): void
    {
        $membershipType = $membership->membershipType;

        if (!$membershipType) {
            $this->skipped++;
            return;
        }

        $primaryMember  = $membership->account?->primaryHolder?->member;
        $hasMultipleClubs = $this->resolveHasMultipleClubs($membership);

        $age = ($this->pricingService->shouldApplyAgeFilter($membershipType) && $primaryMember?->birthdate)
            ? Carbon::parse($primaryMember->birthdate)->age
            : null;

        $rule = $this->pricingService->resolvePricingRule(
            membershipTypeId:     $membership->membership_type_id,
            fromMembershipTypeId: $membership->origin_membership_type_id,
            age:                  $age,
            hasMultipleClubs:     $hasMultipleClubs
        );

        if (!$rule) {
            $this->warn(sprintf(
                '  Sin regla: %s | %s | %s%s',
                $membership->account?->membership_number ?? 'S/N',
                $membership->club?->code              ?? 'N/D',
                $membershipType->code,
                $age !== null ? " | edad={$age}" : ''
            ));
            $this->skipped++;
            return;
        }

        $resolvedFee = $rule->resolveMonthlyFee();

        if ($resolvedFee === null) {
            $this->warn(sprintf(
                '  Regla sin cuota capturada: %s | %s | %s',
                $membership->account?->membership_number ?? 'S/N',
                $membership->club?->code              ?? 'N/D',
                $membershipType->code
            ));
            $this->skipped++;
            return;
        }

        $newFee     = round($resolvedFee, 2);
        $currentFee = round((float) $membership->monthly_fee, 2);

        $this->line(sprintf(
            '  %s %s | %s | %s | antes=$%s → después=$%s%s',
            $newFee !== $currentFee ? '~' : '=',
            $membership->account?->membership_number ?? 'S/N',
            $membership->club?->code              ?? 'N/D',
            $membershipType->code,
            number_format($currentFee, 2),
            number_format($newFee, 2),
            $hasMultipleClubs ? ' [interclub]' : ''
        ));

        if ($newFee === $currentFee) {
            $this->unchanged++;
            return;
        }

        if (!$dryRun) {
            $this->chargeService->synchronizeMembershipFees(
                membership:      $membership,
                groupTotalMonthlyFee: $newFee,
                historyReason:   'Recálculo de cuota por ajuste de precios en catálogo',
                pricingRuleId:   $rule->id,
                interclubPackageRuleId: $membership->interclub_package_rule_id
            );
        }

        $this->updated++;
    }

    protected function resolveHasMultipleClubs(Membership $membership): bool
    {
        $primaryMemberId = $membership->account?->primaryHolder?->member_id;

        if (!$primaryMemberId) {
            return false;
        }

        return Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', '!=', $membership->club_id)
            ->whereHas('account.accountMembers', function (Builder $query) use ($primaryMemberId) {
                $query->where('member_id', $primaryMemberId)
                      ->where('is_primary_holder', true);
            })
            ->exists();
    }
}
