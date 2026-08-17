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

        // Agrupar para no procesar dos veces membresías del mismo grupo
        // interclub — pero SOLO cuando ese grupo realmente representa un
        // combo (interclub_package_rule_id o un pricing_rule con
        // requires_multiple_clubs=true en alguna de sus membresías). Dos
        // cuentas pueden compartir account_group_id sin ser un combo real
        // (mismo titular con productos independientes en cada parque) —
        // ahí cada una debe recalcularse por su cuenta; antes, la segunda
        // se saltaba por completo solo por compartir grupo con la primera.
        $processedGroups = [];

        foreach ($memberships as $membership) {
            $groupId = $membership->account?->account_group_id;
            $isComboGroup = $groupId && $this->groupRepresentsCombo($groupId);

            if ($isComboGroup && in_array($groupId, $processedGroups, true)) {
                continue;
            }

            if ($isComboGroup) {
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

    /**
     * Si esta membresía representa un combo interclub: se toma de su
     * PROPIA clasificación ya asignada (interclub_package_rule_id, o su
     * pricing_rule actual con requires_multiple_clubs=true) — no se
     * re-deriva "¿el mismo titular tiene membresía activa en otro parque?",
     * porque eso da falso positivo con dos membresías independientes del
     * mismo socio (p. ej. un Individual en un parque y un Pase Mensual en
     * otro, sin combo real entre ambos): con la derivación vieja, esta
     * función las marcaba a las dos como "interclub" y buscaba una regla de
     * combo que no debía aplicarles. Este comando solo debe refrescar el
     * monto según la clasificación vigente, no decidir quién es combo.
     */
    protected function resolveHasMultipleClubs(Membership $membership): bool
    {
        return (bool) $membership->interclub_package_rule_id
            || (bool) ($membership->pricingRule?->requires_multiple_clubs ?? false);
    }

    /** @var array<int, bool> */
    protected array $comboGroupCache = [];

    protected function groupRepresentsCombo(int $accountGroupId): bool
    {
        return $this->comboGroupCache[$accountGroupId] ??= Membership::query()
            ->where('is_primary', true)
            ->whereHas('account', fn (Builder $q) => $q->where('account_group_id', $accountGroupId))
            ->where(function (Builder $scope) {
                $scope->whereNotNull('interclub_package_rule_id')
                    ->orWhereHas('pricingRule', fn (Builder $pricingRule) => $pricingRule->where('requires_multiple_clubs', true));
            })
            ->exists();
    }
}
