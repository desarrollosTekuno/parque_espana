<?php

namespace App\Console\Commands;

use App\Models\Catalogs\Relationship;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Services\Billing\MembershipChargeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessMembershipAgeTransitions extends Command
{
    protected $signature = 'memberships:process-age-transitions
        {--date= : Fecha a evaluar en formato YYYY-MM-DD}
        {--dry-run : Solo muestra los cambios sin escribir en base de datos}';

    protected $description = 'Procesa transiciones automáticas por edad entre membresías familiares, solidarias e individuales.';

    protected int $processedFamilyToSolidaria = 0;

    protected int $processedSolidariaToIndividual = 0;

    protected int $skipped = 0;

    public function __construct(
        protected MembershipChargeService $membershipChargeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $asOfDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Procesando transiciones por edad para %s%s',
            $asOfDate->toDateString(),
            $dryRun ? ' (dry-run)' : ''
        ));

        $titularRelationshipId = (int) Relationship::query()
            ->where('name', 'Titular')
            ->value('id');

        if (!$titularRelationshipId) {
            $this->error('No se encontró el parentesco "Titular".');

            return self::FAILURE;
        }

        $this->processFamilyToSolidariaTransitions($asOfDate, $titularRelationshipId, $dryRun);
        $this->processSolidariaToIndividualTransitions($asOfDate, $titularRelationshipId, $dryRun);

        $this->newLine();
        $this->table(
            ['Transición', 'Procesadas'],
            [
                ['Familiar -> Solidaria', $this->processedFamilyToSolidaria],
                ['Solidaria -> Individual', $this->processedSolidariaToIndividual],
                ['Omitidas', $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    protected function processFamilyToSolidariaTransitions(Carbon $asOfDate, int $titularRelationshipId, bool $dryRun): void
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
            ->whereHas('membershipType', function (Builder $query) {
                $query->where('allows_multiple_members', true);
            })
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
                $age = $this->resolveAgeAt($member, $asOfDate);

                if ($age === null) {
                    continue;
                }

                if ($this->memberHasActivePrimaryMembershipInClub($member->id, $familyMembership->club_id)) {
                    $this->warn(sprintf(
                        'Omitido Familiar -> Solidaria para %s en %s: ya tiene una membresía activa propia en ese club.',
                        $this->memberDisplayName($member),
                        $familyMembership->club?->code ?? 'N/D'
                    ));
                    $this->skipped++;
                    continue;
                }

                $hasMultipleClubs = $this->memberHasOtherActiveClubMembership($member->id, $familyMembership->club_id);
                $pricingRule = $this->resolveTransitionPricingRule(
                    fromMembershipType: $familyMembership->membershipType,
                    transitionType: 'family_to_solidaria',
                    age: $age,
                    hasMultipleClubs: $hasMultipleClubs
                );

                if (!$pricingRule) {
                    $this->warn(sprintf(
                        'Omitido Familiar -> Solidaria para %s en %s: no existe una regla de transición aplicable.',
                        $this->memberDisplayName($member),
                        $familyMembership->club?->code ?? 'N/D'
                    ));
                    $this->skipped++;
                    continue;
                }

                $targetMembershipType = MembershipType::find($pricingRule->membership_type_id);

                if (!$targetMembershipType) {
                    $this->warn(sprintf(
                        'Omitido Familiar -> Solidaria para %s: no se encontró el tipo destino.',
                        $this->memberDisplayName($member)
                    ));
                    $this->skipped++;
                    continue;
                }

                $this->line(sprintf(
                    'Familiar -> Solidaria: %s | %s | %s | cuota %s',
                    $this->memberDisplayName($member),
                    $familyMembership->club?->code ?? 'N/D',
                    $targetMembershipType->code,
                    number_format((float) $pricingRule->monthly_fee, 2)
                ));

                if ($dryRun) {
                    $this->processedFamilyToSolidaria++;
                    continue;
                }

                DB::transaction(function () use (
                    $familyMembership,
                    $accountMember,
                    $member,
                    $targetMembershipType,
                    $pricingRule,
                    $titularRelationshipId,
                    $asOfDate
                ) {
                    $newAccount = MembershipAccount::create([
                        'membership_number' => $this->generateMembershipNumber($familyMembership->club),
                        'account_type' => 'individual',
                        'status' => 'active',
                    ]);

                    MembershipAccountMember::create([
                        'membership_account_id' => $newAccount->id,
                        'member_id' => $member->id,
                        'relationship_id' => $titularRelationshipId,
                        'is_primary_holder' => true,
                    ]);

                    $newMembership = Membership::create([
                        'membership_account_id' => $newAccount->id,
                        'club_id' => $familyMembership->club_id,
                        'membership_type_id' => $targetMembershipType->id,
                        'origin_membership_type_id' => $familyMembership->membership_type_id,
                        'is_primary' => true,
                        'is_billable' => true,
                        'monthly_fee' => $pricingRule->monthly_fee,
                        'start_date' => $asOfDate->toDateString(),
                        'end_date' => $targetMembershipType->validity_months
                            ? $asOfDate->copy()->addMonthsNoOverflow($targetMembershipType->validity_months)->toDateString()
                            : null,
                        'status' => 'active',
                    ]);

                    $newMembership->load(['membershipType', 'account.primaryHolder']);

                    $this->membershipChargeService->createInitialCharges(
                        membership: $newMembership,
                        monthlyFee: (float) $pricingRule->monthly_fee,
                        inscriptionFee: (float) ($pricingRule->inscription_fee ?? 0),
                        metadata: [
                            'charge_origin' => 'automatic_family_to_solidaria',
                            'source_membership_id' => $familyMembership->id,
                        ],
                        chargeDate: $asOfDate->copy()
                    );

                    $accountMember->delete();

                    DB::table('memberships.membership_history')->insert([
                        'membership_id' => $newMembership->id,
                        'old_membership_type_id' => $familyMembership->membership_type_id,
                        'new_membership_type_id' => $targetMembershipType->id,
                        'changed_by' => null,
                        'effective_date' => $asOfDate->toDateString(),
                        'reason' => 'Transición automática por edad: Familiar a Solidaria',
                        'previous_monthly_fee' => null,
                        'new_monthly_fee' => $pricingRule->monthly_fee,
                        'metadata' => json_encode([
                            'transition_kind' => 'automatic_family_to_solidaria',
                            'source_membership_id' => $familyMembership->id,
                            'source_account_id' => $familyMembership->membership_account_id,
                            'member_id' => $member->id,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $this->processedFamilyToSolidaria++;
            }
        }
    }

    protected function processSolidariaToIndividualTransitions(Carbon $asOfDate, int $titularRelationshipId, bool $dryRun): void
    {
        $solidariaMemberships = Membership::query()
            ->with([
                'club',
                'membershipType',
                'account.primaryHolder.member',
                'account.accountMembers',
            ])
            ->where('status', 'active')
            ->where('is_primary', true)
            ->whereHas('membershipType', function (Builder $query) {
                $query->where('requires_origin_family', true)
                    ->where('allows_multiple_members', false);
            })
            ->get();

        foreach ($solidariaMemberships as $solidariaMembership) {
            $primaryHolder = $solidariaMembership->account?->primaryHolder?->member;
            $age = $this->resolveAgeAt($primaryHolder, $asOfDate);

            if ($age === null) {
                continue;
            }

            $exitAge = $this->resolveSolidariaExitAge($solidariaMembership->membershipType);

            if ($exitAge === null || $age < $exitAge) {
                continue;
            }

            $hasMultipleClubs = $this->memberHasOtherActiveClubMembership($primaryHolder->id, $solidariaMembership->club_id);
            $pricingRule = $this->resolveTransitionPricingRule(
                fromMembershipType: $solidariaMembership->membershipType,
                transitionType: 'solidaria_to_individual',
                age: $age,
                hasMultipleClubs: $hasMultipleClubs
            );

            if (!$pricingRule) {
                $this->warn(sprintf(
                    'Omitido Solidaria -> Individual para %s en %s: no existe una regla de transición aplicable.',
                    $this->memberDisplayName($primaryHolder),
                    $solidariaMembership->club?->code ?? 'N/D'
                ));
                $this->skipped++;
                continue;
            }

            $targetMembershipType = MembershipType::find($pricingRule->membership_type_id);

            if (!$targetMembershipType) {
                $this->warn(sprintf(
                    'Omitido Solidaria -> Individual para %s: no se encontró el tipo destino.',
                    $this->memberDisplayName($primaryHolder)
                ));
                $this->skipped++;
                continue;
            }

            $this->line(sprintf(
                'Solidaria -> Individual: %s | %s | %s | cuota %s',
                $this->memberDisplayName($primaryHolder),
                $solidariaMembership->club?->code ?? 'N/D',
                $targetMembershipType->code,
                number_format((float) $pricingRule->monthly_fee, 2)
            ));

            if ($dryRun) {
                $this->processedSolidariaToIndividual++;
                continue;
            }

            DB::transaction(function () use (
                $solidariaMembership,
                $primaryHolder,
                $targetMembershipType,
                $pricingRule,
                $titularRelationshipId,
                $asOfDate
            ) {
                $previousMembershipTypeId = $solidariaMembership->membership_type_id;
                $previousMonthlyFee = (float) $solidariaMembership->monthly_fee;

                $solidariaMembership->account->update([
                    'account_type' => 'individual',
                    'status' => 'active',
                ]);

                MembershipAccountMember::query()
                    ->where('membership_account_id', $solidariaMembership->membership_account_id)
                    ->where('member_id', '!=', $primaryHolder->id)
                    ->delete();

                MembershipAccountMember::updateOrCreate([
                    'membership_account_id' => $solidariaMembership->membership_account_id,
                    'member_id' => $primaryHolder->id,
                ], [
                    'relationship_id' => $titularRelationshipId,
                    'is_primary_holder' => true,
                ]);

                $solidariaMembership->update([
                    'membership_type_id' => $targetMembershipType->id,
                    'origin_membership_type_id' => $previousMembershipTypeId,
                    'is_primary' => true,
                    'is_billable' => $solidariaMembership->is_billable,
                    'monthly_fee' => $pricingRule->monthly_fee,
                    'start_date' => $asOfDate->toDateString(),
                    'end_date' => $targetMembershipType->validity_months
                        ? $asOfDate->copy()->addMonthsNoOverflow($targetMembershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'active',
                ]);

                $solidariaMembership->load(['membershipType', 'account.primaryHolder']);

                $this->membershipChargeService->createInitialCharges(
                    membership: $solidariaMembership,
                    monthlyFee: (float) $pricingRule->monthly_fee,
                    inscriptionFee: (float) ($pricingRule->inscription_fee ?? 0),
                    metadata: [
                        'charge_origin' => 'automatic_solidaria_to_individual',
                        'previous_membership_type_id' => $previousMembershipTypeId,
                        'new_membership_type_id' => $targetMembershipType->id,
                    ],
                    chargeDate: $asOfDate->copy(),
                    reconcileExistingMonthlyCharge: true
                );

                DB::table('memberships.membership_history')->insert([
                    'membership_id' => $solidariaMembership->id,
                    'old_membership_type_id' => $previousMembershipTypeId,
                    'new_membership_type_id' => $targetMembershipType->id,
                    'changed_by' => null,
                    'effective_date' => $asOfDate->toDateString(),
                    'reason' => 'Transición automática por edad: Solidaria a Individual',
                    'previous_monthly_fee' => $previousMonthlyFee,
                    'new_monthly_fee' => $pricingRule->monthly_fee,
                    'metadata' => json_encode([
                        'transition_kind' => 'automatic_solidaria_to_individual',
                        'membership_id' => $solidariaMembership->id,
                        'membership_account_id' => $solidariaMembership->membership_account_id,
                        'member_id' => $primaryHolder->id,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $this->processedSolidariaToIndividual++;
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
                fn (Builder $query) => $query->where('requires_origin_family', true)
                    ->where('allows_multiple_members', false)
            )
            ->when(
                $transitionType === 'solidaria_to_individual',
                fn (Builder $query) => $query->where('requires_origin_family', false)
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
                    function (Builder $query) use ($age) {
                        $query->where(function (Builder $ageQuery) use ($age) {
                            $ageQuery->whereNull('min_age')->orWhere('min_age', '<=', $age);
                        })->where(function (Builder $ageQuery) use ($age) {
                            $ageQuery->whereNull('max_age')->orWhere('max_age', '>=', $age);
                        });
                    },
                    fn (Builder $query) => $query->whereNull('min_age')->whereNull('max_age')
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

        if ($maxAge === null) {
            return null;
        }

        return (int) $maxAge + 1;
    }

    protected function memberHasOtherActiveClubMembership(int $memberId, int $currentClubId): bool
    {
        return Membership::query()
            ->where('status', 'active')
            ->where('club_id', '!=', $currentClubId)
            ->whereHas('account.accountMembers', function (Builder $query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->exists();
    }

    protected function memberHasActivePrimaryMembershipInClub(int $memberId, int $clubId): bool
    {
        return Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', $clubId)
            ->whereHas('account.primaryHolder', function (Builder $query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->exists();
    }

    protected function resolveAgeAt(?Member $member, Carbon $asOfDate): ?int
    {
        if (!$member?->birthdate) {
            return null;
        }

        return Carbon::parse($member->birthdate)->diffInYears($asOfDate);
    }

    protected function generateMembershipNumber($club): string
    {
        do {
            $membershipNumber = sprintf(
                '%s-%s%s',
                Str::upper($club->code ?: 'MEM'),
                now()->format('YmdHisv'),
                Str::upper(Str::random(3))
            );
        } while (MembershipAccount::query()->where('membership_number', $membershipNumber)->exists());

        return $membershipNumber;
    }

    protected function memberDisplayName(?Member $member): string
    {
        if (!$member) {
            return 'Miembro sin nombre';
        }

        return trim(collect([
            $member->first_name,
            $member->last_name,
            $member->second_last_name,
        ])->filter()->implode(' '));
    }
}
