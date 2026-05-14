<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Catalogs\Relationship;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\PendingAgeTransition;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\MembershipPricingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AgeTransitionController extends Controller
{
    public function __construct(
        protected MembershipChargeService $chargeService,
        protected MembershipPricingService $pricingService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $clubId = session('club_id');
            $prefix = 'age_transitions';
            $driver = DB::getDriverName();

            $query = PendingAgeTransition::query()
                ->with([
                    'member',
                    'targetMembershipType',
                    'membership.club',
                    'membership.membershipType',
                    'promotedBy',
                    'dismissedBy',
                ])
                ->whereHas('membership', fn (Builder $q) => $q->where('club_id', $clubId));

            $statusFilter = $request->input("{$prefix}_status", 'pending');
            if ($statusFilter && $statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';
                $query->whereHas('member', fn (Builder $q) =>
                    $q->where('first_name', $like, "%{$search}%")
                      ->orWhere('last_name', $like, "%{$search}%")
                      ->orWhere('second_last_name', $like, "%{$search}%")
                );
            }

            if ($transitionType = $request->input("{$prefix}_transition_type")) {
                $query->where('transition_type', $transitionType);
            }

            $transitions = $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('identified_at', 'desc')
                ->paginate(
                    $request->input("{$prefix}_per_page", 15),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(fn (PendingAgeTransition $t) => $this->formatTransition($t))
                ->appends($request->all());

            return Inertia::render('Members/AgeTransitions', [
                'transitions' => $transitions,
                'filters' => [
                    'search'          => $request->input("{$prefix}_search"),
                    'status'          => $statusFilter,
                    'transition_type' => $request->input("{$prefix}_transition_type"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('Members/AgeTransitions', [
                'transitions' => ['data' => [], 'total' => 0],
                'filters'     => [],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function promote(Request $request, PendingAgeTransition $ageTransition)
    {
        try {
            $clubId = session('club_id');

            if ((int) $ageTransition->membership->club_id !== (int) $clubId) {
                abort(404);
            }

            if ($ageTransition->status !== 'pending') {
                return redirect()->back()->withErrors([
                    'messageError' => 'Esta transición ya fue procesada.',
                    'exception'    => '',
                ]);
            }

            $ageTransition->load([
                'membership.club',
                'membership.membershipType',
                'membership.account.accountMembers.member',
                'membership.account.accountGroup',
                'member',
                'targetMembershipType',
            ]);

            match ($ageTransition->transition_type) {
                'family_to_solidaria'   => $this->promoteFamilyToSolidaria($ageTransition),
                'solidaria_to_individual' => $this->promoteSolidariaToIndividual($ageTransition),
                default => abort(422, 'Tipo de transición desconocido.'),
            };

            return redirect()->back()->with('success', 'Transición promovida correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al promover la transición: ' . $e->getMessage(),
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function dismiss(Request $request, PendingAgeTransition $ageTransition)
    {
        try {
            $clubId = session('club_id');

            if ((int) $ageTransition->membership->club_id !== (int) $clubId) {
                abort(404);
            }

            if ($ageTransition->status !== 'pending') {
                return redirect()->back()->withErrors([
                    'messageError' => 'Esta transición ya fue procesada.',
                    'exception'    => '',
                ]);
            }

            $validated = $request->validate([
                'dismissal_reason' => ['nullable', 'string', 'max:255'],
            ]);

            $ageTransition->update([
                'status'           => 'dismissed',
                'dismissed_at'     => now(),
                'dismissed_by'     => auth()->id(),
                'dismissal_reason' => $validated['dismissal_reason'] ?? null,
            ]);

            return redirect()->back()->with('success', 'Transición descartada.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al descartar la transición.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function promoteFamilyToSolidaria(PendingAgeTransition $transition): void
    {
        $familyMembership = $transition->membership;
        $member           = $transition->member;
        $targetType       = $transition->targetMembershipType;

        $accountMember = $familyMembership->account->accountMembers
            ->firstWhere('member_id', $member->id);

        if (!$accountMember) {
            throw new \RuntimeException(
                "El integrante {$member->id} ya no pertenece a la cuenta familiar."
            );
        }

        if ($accountMember->is_primary_holder) {
            throw new \RuntimeException('El titular principal no puede ser promovido con este flujo.');
        }

        $titularRelationshipId = Relationship::query()
            ->where('name', 'Titular')
            ->value('id');

        // Reuse existing account group if the member already holds another club primary
        $existingAccountGroup = $this->findExistingAccountGroup(
            $member->id,
            $familyMembership->club_id
        );

        DB::transaction(function () use (
            $transition, $familyMembership, $member, $targetType,
            $accountMember, $titularRelationshipId, $existingAccountGroup
        ) {
            $group = $existingAccountGroup ?? MembershipAccountGroup::create([
                'status' => 'active',
            ]);

            $newAccount = \App\Models\Memberships\MembershipAccount::create([
                'account_group_id'  => $group->id,
                'club_id'           => $familyMembership->club_id,
                'membership_number' => $this->generateMembershipNumber($familyMembership->club),
                'account_type'      => 'individual',
                'status'            => 'active',
                'origin_account_id' => $familyMembership->membership_account_id,
                'separation_reason' => 'Promoción por edad',
            ]);

            MembershipAccountMember::create([
                'membership_account_id' => $newAccount->id,
                'member_id'             => $member->id,
                'relationship_id'       => $titularRelationshipId ?: $accountMember->relationship_id,
                'is_primary_holder'     => true,
            ]);

            $newMembership = Membership::create([
                'membership_account_id'    => $newAccount->id,
                'club_id'                  => $familyMembership->club_id,
                'membership_type_id'       => $targetType->id,
                'origin_membership_type_id' => $familyMembership->membership_type_id,
                'is_primary'               => true,
                'is_billable'              => true,
                'monthly_fee'              => $transition->monthly_fee,
                'monthly_fee_total'        => $transition->monthly_fee,
                'monthly_fee_share'        => $transition->monthly_fee,
                'billing_split_mode'       => 'single',
                'start_date'               => now()->toDateString(),
                'end_date'                 => $targetType->validity_months
                    ? now()->addMonthsNoOverflow($targetType->validity_months)->toDateString()
                    : null,
                'status'                   => 'active',
            ]);

            $newMembership = $this->chargeService
                ->synchronizeMembershipFees(
                    $newMembership,
                    (float) $transition->monthly_fee,
                    null,
                    'single',
                    'Promoción por edad — ' . $targetType->name
                )
                ->firstWhere('id', $newMembership->id)
                ?? $newMembership->fresh(['membershipType', 'account.primaryHolder']);

            $this->chargeService->createInitialCharges(
                membership: $newMembership,
                monthlyFee: (float) $transition->monthly_fee,
                inscriptionFee: 0.0,
                metadata: [
                    'charge_origin'             => 'age_transition',
                    'source_membership_id'      => $familyMembership->id,
                    'pending_age_transition_id' => $transition->id,
                ],
                chargeDate: now()
            );

            $accountMember->delete();

            DB::table('memberships.membership_history')->insert([
                'membership_id'         => $newMembership->id,
                'old_membership_type_id' => $familyMembership->membership_type_id,
                'new_membership_type_id' => $targetType->id,
                'changed_by'            => auth()->id(),
                'effective_date'        => now()->toDateString(),
                'reason'                => 'Promoción por edad desde cuenta familiar',
                'previous_monthly_fee'  => null,
                'new_monthly_fee'       => $transition->monthly_fee,
                'metadata'              => json_encode([
                    'transition_kind'           => 'age_transition_family_to_solidaria',
                    'source_membership_id'      => $familyMembership->id,
                    'pending_age_transition_id' => $transition->id,
                ]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $transition->update([
                'status'      => 'promoted',
                'promoted_at' => now(),
                'promoted_by' => auth()->id(),
            ]);
        });
    }

    private function promoteSolidariaToIndividual(PendingAgeTransition $transition): void
    {
        $solidariaMembership = $transition->membership;
        $targetType          = $transition->targetMembershipType;

        $previousTypeId  = $solidariaMembership->membership_type_id;
        $previousFee     = (float) $solidariaMembership->monthly_fee;

        DB::transaction(function () use ($transition, $solidariaMembership, $targetType, $previousTypeId, $previousFee) {
            $solidariaMembership->update([
                'membership_type_id'       => $targetType->id,
                'origin_membership_type_id' => $previousTypeId,
                'monthly_fee'              => $transition->monthly_fee,
                'monthly_fee_total'        => $transition->monthly_fee,
                'monthly_fee_share'        => $transition->monthly_fee,
                'billing_split_mode'       => 'single',
                'start_date'               => now()->toDateString(),
                'end_date'                 => $targetType->validity_months
                    ? now()->addMonthsNoOverflow($targetType->validity_months)->toDateString()
                    : null,
            ]);

            DB::table('memberships.membership_history')->insert([
                'membership_id'         => $solidariaMembership->id,
                'old_membership_type_id' => $previousTypeId,
                'new_membership_type_id' => $targetType->id,
                'changed_by'            => auth()->id(),
                'effective_date'        => now()->toDateString(),
                'reason'                => 'Promoción por edad de solidaria a individual',
                'previous_monthly_fee'  => $previousFee,
                'new_monthly_fee'       => $transition->monthly_fee,
                'metadata'              => json_encode([
                    'transition_kind'           => 'age_transition_solidaria_to_individual',
                    'pending_age_transition_id' => $transition->id,
                ]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $solidariaMembership = $this->chargeService
                ->synchronizeMembershipFees(
                    $solidariaMembership,
                    (float) $transition->monthly_fee,
                    null,
                    'single',
                    'Promoción por edad — ' . $targetType->name
                )
                ->firstWhere('id', $solidariaMembership->id)
                ?? $solidariaMembership->fresh(['membershipType', 'account.primaryHolder']);

            $this->chargeService->createInitialCharges(
                membership: $solidariaMembership,
                monthlyFee: (float) $transition->monthly_fee,
                inscriptionFee: 0.0,
                metadata: [
                    'charge_origin'             => 'age_transition',
                    'previous_membership_type_id' => $previousTypeId,
                    'pending_age_transition_id' => $transition->id,
                ],
                chargeDate: now(),
                reconcileExistingMonthlyCharge: true
            );

            $transition->update([
                'status'      => 'promoted',
                'promoted_at' => now(),
                'promoted_by' => auth()->id(),
            ]);
        });
    }

    private function findExistingAccountGroup(int $memberId, int $currentClubId): ?MembershipAccountGroup
    {
        $existing = Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', '!=', $currentClubId)
            ->whereHas('account.accountMembers', fn (Builder $q) =>
                $q->where('member_id', $memberId)->where('is_primary_holder', true)
            )
            ->with('account.accountGroup')
            ->first();

        return $existing?->account?->accountGroup;
    }

    private function generateMembershipNumber(\App\Models\Administrator\Club $club): string
    {
        return sprintf(
            '%s-%s',
            strtoupper($club->code ?: 'MEM'),
            now()->format('YmdHisv')
        );
    }

    private function formatTransition(PendingAgeTransition $t): array
    {
        $member = $t->member;
        $name   = $member
            ? trim(collect([$member->first_name, $member->last_name, $member->second_last_name])->filter()->implode(' '))
            : 'N/D';

        return [
            'id'                      => $t->id,
            'member_id'               => $t->member_id,
            'member_name'             => $name,
            'membership_id'           => $t->membership_id,
            'membership_account_id'   => $t->membership_account_id,
            'club_code'               => $t->membership?->club?->code,
            'from_membership_type'    => $t->membership?->membershipType?->name,
            'target_membership_type'  => $t->targetMembershipType?->name,
            'target_membership_type_id' => $t->target_membership_type_id,
            'transition_type'         => $t->transition_type,
            'monthly_fee'             => (float) $t->monthly_fee,
            'has_multiple_clubs'      => (bool) $t->has_multiple_clubs,
            'status'                  => $t->status,
            'identified_at'           => $t->identified_at?->toDateTimeString(),
            'promoted_at'             => $t->promoted_at?->toDateTimeString(),
            'promoted_by_name'        => $t->promotedBy?->name,
            'dismissed_at'            => $t->dismissed_at?->toDateTimeString(),
            'dismissed_by_name'       => $t->dismissedBy?->name,
            'dismissal_reason'        => $t->dismissal_reason,
        ];
    }
}
