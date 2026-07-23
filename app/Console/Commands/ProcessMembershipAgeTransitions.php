<?php

namespace App\Console\Commands;

use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PendingAgeTransition;
use App\Models\Memberships\PricingRule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ProcessMembershipAgeTransitions extends Command
{
    protected $signature = 'memberships:process-age-transitions
        {--date= : Fecha a evaluar en formato YYYY-MM-DD}
        {--dry-run : Solo muestra los cambios sin escribir en base de datos}';

    protected $description = 'Identifica integrantes que deben ser promovidos por edad y los registra como transiciones pendientes para aprobación manual.';

    protected int $inserted   = 0;
    protected int $alreadyPending = 0;
    protected int $skipped    = 0;

    public function handle(): int
    {
        $asOfDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Identificando transiciones por edad para %s%s',
            $asOfDate->toDateString(),
            $dryRun ? ' (dry-run)' : ''
        ));

        $this->detectFamilyToSolidaria($asOfDate, $dryRun);
        $this->detectSolidariaToIndividual($asOfDate, $dryRun);

        $this->newLine();
        $this->table(
            ['Estado', 'Cantidad'],
            [
                ['Nuevos pendientes registrados', $this->inserted],
                ['Ya estaban pendientes',         $this->alreadyPending],
                ['Omitidos (sin regla o duplicado activo)', $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    protected function detectFamilyToSolidaria(Carbon $asOfDate, bool $dryRun): void
    {
        $familyMemberships = Membership::query()
            ->with([
                'club',
                'membershipType',
                'account.accountMembers.relationship',
                'account.accountMembers.member',
            ])
            ->where('status', 'active')
            ->where('is_primary', true)
            ->whereHas('membershipType', fn (Builder $q) => $q->where('allows_multiple_members', true))
            ->orderBy('club_id')
            ->orderBy('id')
            ->get();

        foreach ($familyMemberships as $familyMembership) {
            foreach ($familyMembership->account->accountMembers as $accountMember) {
                if ($accountMember->is_primary_holder) {
                    continue;
                }

                if ($accountMember->relationship?->name !== 'Hijo(a)') {
                    continue;
                }

                $member = $accountMember->member;
                $age    = $this->resolveAgeAt($member, $asOfDate);

                if ($age === null) {
                    continue;
                }

                if ($this->memberHasActivePrimaryMembershipInClub($member->id, $familyMembership->club_id)) {
                    $this->warn(sprintf(
                        'Omitido Familiar→Solidaria para %s en %s: ya tiene membresía propia activa.',
                        $this->memberDisplayName($member),
                        $familyMembership->club?->code ?? 'N/D'
                    ));
                    $this->skipped++;
                    continue;
                }

                $hasMultipleClubs = $this->memberHasOtherActiveClubMembership($member->id, $familyMembership->club_id);
                $pricingRule      = $this->resolveTransitionPricingRule(
                    fromMembershipType: $familyMembership->membershipType,
                    transitionType: 'family_to_solidaria',
                    age: $age,
                    hasMultipleClubs: $hasMultipleClubs
                );

                if (!$pricingRule) {
                    $this->warn(sprintf(
                        'Omitido Familiar→Solidaria para %s en %s: sin regla aplicable.',
                        $this->memberDisplayName($member),
                        $familyMembership->club?->code ?? 'N/D'
                    ));
                    $this->skipped++;
                    continue;
                }

                $targetType = MembershipType::find($pricingRule->membership_type_id);
                if (!$targetType) {
                    $this->skipped++;
                    continue;
                }

                $monthlyFee = $pricingRule->resolveMonthlyFee();
                if ($monthlyFee === null) {
                    $this->warn(sprintf(
                        'Omitido Familiar→Solidaria para %s en %s: la regla encontrada no tiene cuota capturada.',
                        $this->memberDisplayName($member),
                        $familyMembership->club?->code ?? 'N/D'
                    ));
                    $this->skipped++;
                    continue;
                }

                $this->line(sprintf(
                    'Pendiente Familiar→Solidaria: %s | %s | %s | $%s',
                    $this->memberDisplayName($member),
                    $familyMembership->club?->code ?? 'N/D',
                    $targetType->code,
                    number_format($monthlyFee, 2)
                ));

                if ($dryRun) {
                    $this->inserted++;
                    continue;
                }

                $alreadyExists = PendingAgeTransition::query()
                    ->where('membership_account_id', $familyMembership->membership_account_id)
                    ->where('member_id', $member->id)
                    ->where('transition_type', 'family_to_solidaria')
                    ->where('status', 'pending')
                    ->exists();

                if ($alreadyExists) {
                    $this->alreadyPending++;
                    continue;
                }

                PendingAgeTransition::create([
                    'membership_id'            => $familyMembership->id,
                    'member_id'                => $member->id,
                    'membership_account_id'    => $familyMembership->membership_account_id,
                    'target_membership_type_id' => $targetType->id,
                    'transition_type'          => 'family_to_solidaria',
                    'monthly_fee'              => $monthlyFee,
                    'has_multiple_clubs'       => $hasMultipleClubs,
                    'status'                   => 'pending',
                    'identified_at'            => now(),
                ]);

                $this->inserted++;
            }
        }
    }

    protected function detectSolidariaToIndividual(Carbon $asOfDate, bool $dryRun): void
    {
        $solidariaMemberships = Membership::query()
            ->with([
                'club',
                'membershipType',
                'account.primaryHolder.member',
            ])
            ->where('status', 'active')
            ->where('is_primary', true)
            ->whereHas('membershipType', fn (Builder $q) =>
                $q->where('requires_origin_family', true)->where('allows_multiple_members', false)
            )
            ->get();

        foreach ($solidariaMemberships as $solidariaMembership) {
            $primaryHolder = $solidariaMembership->account?->primaryHolder?->member;
            $age           = $this->resolveAgeAt($primaryHolder, $asOfDate);

            if ($age === null) {
                continue;
            }

            $exitAge = $this->resolveSolidariaExitAge($solidariaMembership->membershipType);

            if ($exitAge === null || $age < $exitAge) {
                continue;
            }

            $hasMultipleClubs = $this->memberHasOtherActiveClubMembership($primaryHolder->id, $solidariaMembership->club_id);
            $pricingRule      = $this->resolveTransitionPricingRule(
                fromMembershipType: $solidariaMembership->membershipType,
                transitionType: 'solidaria_to_individual',
                age: $age,
                hasMultipleClubs: $hasMultipleClubs
            );

            if (!$pricingRule) {
                $this->warn(sprintf(
                    'Omitido Solidaria→Individual para %s en %s: sin regla aplicable.',
                    $this->memberDisplayName($primaryHolder),
                    $solidariaMembership->club?->code ?? 'N/D'
                ));
                $this->skipped++;
                continue;
            }

            $targetType = MembershipType::find($pricingRule->membership_type_id);
            if (!$targetType) {
                $this->skipped++;
                continue;
            }

            $monthlyFee = $pricingRule->resolveMonthlyFee();
            if ($monthlyFee === null) {
                $this->warn(sprintf(
                    'Omitido Solidaria→Individual para %s en %s: la regla encontrada no tiene cuota capturada.',
                    $this->memberDisplayName($primaryHolder),
                    $solidariaMembership->club?->code ?? 'N/D'
                ));
                $this->skipped++;
                continue;
            }

            $this->line(sprintf(
                'Pendiente Solidaria→Individual: %s | %s | %s | $%s',
                $this->memberDisplayName($primaryHolder),
                $solidariaMembership->club?->code ?? 'N/D',
                $targetType->code,
                number_format($monthlyFee, 2)
            ));

            if ($dryRun) {
                $this->inserted++;
                continue;
            }

            $alreadyExists = PendingAgeTransition::query()
                ->where('membership_account_id', $solidariaMembership->membership_account_id)
                ->where('member_id', $primaryHolder->id)
                ->where('transition_type', 'solidaria_to_individual')
                ->where('status', 'pending')
                ->exists();

            if ($alreadyExists) {
                $this->alreadyPending++;
                continue;
            }

            PendingAgeTransition::create([
                'membership_id'            => $solidariaMembership->id,
                'member_id'                => $primaryHolder->id,
                'membership_account_id'    => $solidariaMembership->membership_account_id,
                'target_membership_type_id' => $targetType->id,
                'transition_type'          => 'solidaria_to_individual',
                'monthly_fee'              => $monthlyFee,
                'has_multiple_clubs'       => $hasMultipleClubs,
                'status'                   => 'pending',
                'identified_at'            => now(),
            ]);

            $this->inserted++;
        }
    }

    protected function resolveTransitionPricingRule(
        MembershipType $fromMembershipType,
        string $transitionType,
        ?int $age,
        bool $hasMultipleClubs
    ): ?PricingRule {
        $candidateTargetIds = MembershipType::query()
            ->where('club_id', $fromMembershipType->club_id)
            ->when(
                $transitionType === 'family_to_solidaria',
                fn (Builder $q) => $q->where('requires_origin_family', true)->where('allows_multiple_members', false)
            )
            ->when(
                $transitionType === 'solidaria_to_individual',
                fn (Builder $q) => $q->where('requires_origin_family', false)
                    ->where('allows_multiple_members', false)
                    ->where('show_in_listing', true)
            )
            ->pluck('id');

        if ($candidateTargetIds->isEmpty()) {
            return null;
        }

        $attempts = $hasMultipleClubs ? [true, false] : [false];

        foreach ($attempts as $requiresMultipleClubs) {
            $rule = PricingRule::query()
                ->whereIn('membership_type_id', $candidateTargetIds)
                ->where('from_membership_type_id', $fromMembershipType->id)
                ->when(
                    $age !== null,
                    function (Builder $q) use ($age) {
                        $q->where(fn (Builder $a) => $a->whereNull('min_age')->orWhere('min_age', '<=', $age))
                          ->where(fn (Builder $a) => $a->whereNull('max_age')->orWhere('max_age', '>=', $age));
                    },
                    fn (Builder $q) => $q->whereNull('min_age')->whereNull('max_age')
                )
                ->where('requires_multiple_clubs', $requiresMultipleClubs)
                ->orderBy('priority')
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return null;
    }

    protected function resolveSolidariaExitAge(MembershipType $solidariaMembershipType): ?int
    {
        $maxAge = PricingRule::query()
            ->where('membership_type_id', $solidariaMembershipType->id)
            ->max('max_age');

        return $maxAge !== null ? (int) $maxAge + 1 : null;
    }

    protected function memberHasOtherActiveClubMembership(int $memberId, int $currentClubId): bool
    {
        return Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', '!=', $currentClubId)
            ->whereHas('account.accountMembers', fn (Builder $q) =>
                $q->where('member_id', $memberId)->where('is_primary_holder', true)
            )
            ->exists();
    }

    protected function memberHasActivePrimaryMembershipInClub(int $memberId, int $clubId): bool
    {
        return Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', $clubId)
            ->whereHas('account.primaryHolder', fn (Builder $q) => $q->where('member_id', $memberId))
            ->exists();
    }

    protected function resolveAgeAt(?Member $member, Carbon $asOfDate): ?int
    {
        if (!$member?->birthdate) {
            return null;
        }

        return Carbon::parse($member->birthdate)->diffInYears($asOfDate);
    }

    protected function memberDisplayName(?Member $member): string
    {
        if (!$member) {
            return 'Miembro sin nombre';
        }

        return trim(collect([$member->first_name, $member->last_name, $member->second_last_name])->filter()->implode(' '));
    }
}
