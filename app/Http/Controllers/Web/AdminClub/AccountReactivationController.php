<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Memberships\AccountReactivation;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccountMember;
use App\Services\Access\AccessProvisioningService;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\MembershipPricingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AccountReactivationController extends Controller
{
    public function __construct(
        protected MembershipChargeService $membershipChargeService,
        protected MembershipPricingService $membershipPricingService,
        protected AccessProvisioningService $accessProvisioningService
    ) {
        $this->middleware('permission:members.reactivate');
    }

    public function create(Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        if (!$membership->is_primary) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo se puede reactivar una membresía principal.',
                'exception' => '',
            ]);
        }

        $membership->load([
            'account.primaryHolder.member',
            'account.accountMembers.member',
            'account.accountMembers.relationship',
            'account.memberships.membershipType',
            'account.cancelledBy',
            'account.cancellationReason',
            'account.reactivations.reactivatedBy',
            'membershipType',
            'club',
        ]);

        $account = $membership->account;

        if (!$account || $account->status !== 'cancelled') {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo se puede reactivar una cuenta cancelada.',
                'exception' => '',
            ]);
        }

        // Criterion #7: sanction-based cancellations cannot be reactivated from this view
        if ($account->cancellation_type === 'sanction') {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Las cuentas dadas de baja por sanción no pueden reactivarse desde esta vista.',
                'exception' => '',
            ]);
        }

        $primaryHolder = $account->primaryHolder?->member;

        $members = $account->accountMembers->map(function (MembershipAccountMember $accountMember) {
            $member = $accountMember->member;
            return [
                'member_id' => $accountMember->member_id,
                'full_name' => trim(collect([
                    $member?->first_name,
                    $member?->last_name,
                    $member?->second_last_name,
                ])->filter()->implode(' ')),
                'relationship_name' => $accountMember->relationship?->name,
                'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                'email' => $member?->email,
                'phone' => $member?->phone,
            ];
        })->values();

        $previousMemberships = $account->memberships->map(function (Membership $m) {
            return [
                'id' => $m->id,
                'membership_type_name' => $m->membershipType?->name,
                'membership_type_code' => $m->membershipType?->code,
                'monthly_fee' => (float) $m->monthly_fee,
                'start_date' => $m->start_date,
                'end_date' => $m->end_date,
                'status' => $m->status,
                'is_primary' => (bool) $m->is_primary,
            ];
        })->values();

        $reactivationHistory = $account->reactivations->map(function (AccountReactivation $r) {
            return [
                'reactivated_at' => $r->reactivated_at,
                'reactivated_by_name' => $r->reactivatedBy?->name,
                'requires_documentation' => $r->requires_documentation,
                'enrollment_fee_applied' => $r->enrollment_fee_applied,
                'enrollment_fee_amount' => $r->enrollment_fee_amount,
                'notes' => $r->notes,
            ];
        })->values();

        return Inertia::render('Members/Reactivate', [
            'membership' => [
                'id' => $membership->id,
                'membership_number' => $account->membership_number,
                'holder_name' => trim(collect([
                    $primaryHolder?->first_name,
                    $primaryHolder?->last_name,
                    $primaryHolder?->second_last_name,
                ])->filter()->implode(' ')),
                'membership_type_name' => $membership->membershipType?->name,
                'club_name' => $membership->club?->name,
                'status' => $account->status,
                'cancelled_at' => $account->cancelled_at,
                'cancelled_by_name' => $account->cancelledBy?->name,
                'cancellation_type' => $account->cancellation_type,
                'cancellation_reason' => $account->cancellationReason?->name,
            ],
            'members' => $members,
            'previous_memberships' => $previousMemberships,
            'reactivation_history' => $reactivationHistory,
        ]);
    }

    public function store(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        if (!$membership->is_primary) {
            return back()->withErrors([
                'messageError' => 'Solo se puede reactivar una membresía principal.',
                'exception' => '',
            ]);
        }

        $membership->load(['account.memberships', 'account.primaryHolder.member', 'account.accountMembers']);

        $account = $membership->account;

        if (!$account || $account->status !== 'cancelled') {
            return back()->withErrors([
                'messageError' => 'Solo se puede reactivar una cuenta cancelada.',
                'exception' => '',
            ]);
        }

        if ($account->cancellation_type === 'sanction') {
            return back()->withErrors([
                'messageError' => 'Las cuentas dadas de baja por sanción no pueden reactivarse desde esta vista.',
                'exception' => '',
            ]);
        }

        $request->validate([
            'apply_enrollment_fee' => ['required', 'boolean'],
            'enrollment_fee_amount' => ['required_if:apply_enrollment_fee,true', 'nullable', 'numeric', 'min:0.01'],
            'enrollment_fee_installment_months' => ['nullable', 'integer', 'min:2', 'max:12'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirmed' => ['accepted'],
        ], [
            'apply_enrollment_fee.required' => 'Indica si aplica cobro de reinscripción.',
            'enrollment_fee_amount.required_if' => 'El monto de reinscripción es requerido cuando se aplica el cobro.',
            'enrollment_fee_amount.min' => 'El monto de reinscripción debe ser mayor a cero.',
            'confirmed.accepted' => 'Debes confirmar la reactivación antes de continuar.',
        ]);

        $primaryHolder = $account->primaryHolder?->member;

        if (!$primaryHolder) {
            return back()->withErrors([
                'messageError' => 'No se encontró el titular de la cuenta.',
                'exception' => '',
            ]);
        }

        DB::beginTransaction();
        try {
            $now = now();

            // Restore account to active, preserve cancellation history via the reactivation record
            $account->update([
                'status' => 'active',
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_letter_path' => null,
                'cancellation_type' => null,
                'cancellation_reason_id' => null,
            ]);

            // Restore all cancelled memberships for this account back to active
            $cancelledMemberships = $account->memberships()->where('status', 'cancelled')->get();

            $account->memberships()->where('status', 'cancelled')->update([
                'status' => 'active',
                'end_date' => null,
            ]);

            foreach ($cancelledMemberships as $m) {
                DB::table('memberships.membership_history')->insert([
                    'membership_id'          => $m->id,
                    'old_membership_type_id' => $m->membership_type_id,
                    'new_membership_type_id' => $m->membership_type_id,
                    'changed_by'             => auth()->id(),
                    'effective_date'         => $now->toDateString(),
                    'reason'                 => 'Reactivación de cuenta',
                    'previous_monthly_fee'   => null,
                    'new_monthly_fee'        => (float) $m->monthly_fee,
                    'metadata'               => json_encode(['reactivation_origin' => 'manual']),
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ]);
            }

            // Recalculate group fees so the interclub/split rate is re-established
            // if this account belongs to a group with other active memberships.
            $reactivatedPrimaryMembership = $account->memberships()
                ->where('is_primary', true)
                ->where('status', 'active')
                ->first();

            if ($reactivatedPrimaryMembership) {
                $this->membershipPricingService->recalculateGroupFeesAfterReactivation(
                    $reactivatedPrimaryMembership,
                    $this->membershipChargeService
                );

                // After fee recalculation, generate an adjustment charge for the current
                // month if the new group total exceeds what was already billed this period.
                // Example: Park 2 had been cancelled, Park 1 was billed 3,600 standalone.
                // On reactivation the interclub rate is 3,700 → generates a 100 adjustment.
                $reactivatedPrimaryMembership->refresh();
                $newGroupTotal = (float) $reactivatedPrimaryMembership->resolved_monthly_fee_total;

                if ($newGroupTotal > 0) {
                    $this->membershipChargeService->createInitialCharges(
                        membership: $reactivatedPrimaryMembership,
                        monthlyFee: $newGroupTotal,
                        inscriptionFee: 0,
                        metadata: ['charge_origin' => 'account_reactivation'],
                        chargeDate: $now,
                        reconcileExistingMonthlyCharge: true
                    );
                }
            }

            // Apply reinscripción fee charge if requested. Uses createInstallmentCharge
            // (extracted from createInitialCharges) so it never touches the monthly-fee
            // reconciliation logic already run above for this same transaction.
            if ($request->boolean('apply_enrollment_fee') && $request->filled('enrollment_fee_amount')) {
                $this->membershipChargeService->createInstallmentCharge(
                    membership: $membership,
                    conceptCode: 'CUOTA_REINSCRIPCION',
                    totalAmount: (float) $request->input('enrollment_fee_amount'),
                    installmentMonths: $request->integer('enrollment_fee_installment_months') ?: null,
                    metadata: [
                        'origin' => 'account_reactivation',
                        'reactivated_at' => $now->toDateTimeString(),
                    ],
                    chargeDate: $now
                );
            }

            // Log the reactivation event
            AccountReactivation::create([
                'membership_account_id' => $account->id,
                'reactivated_by' => auth()->id(),
                'reactivated_at' => $now,
                'requires_documentation' => false,
                'enrollment_fee_applied' => $request->boolean('apply_enrollment_fee'),
                'enrollment_fee_amount' => $request->boolean('apply_enrollment_fee')
                    ? (float) $request->input('enrollment_fee_amount')
                    : null,
                'notes' => $request->input('notes'),
            ]);

            DB::commit();

            foreach ($account->accountMembers as $accountMember)
            {
                try {
                    $this->accessProvisioningService->provision($accountMember, $account);
                } catch (\Throwable $e) {
                    Log::error('Error al reaprovisionar acceso físico tras reactivación', [
                        'account_member_id' => $accountMember->id,
                        'member_id' => $accountMember->member_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Cuenta reactivada', [
                'membership_account_id' => $account->id,
                'reactivated_by' => auth()->id(),
            ]);

            return redirect()->route('members.index')
                ->with('success', "La cuenta #{$account->membership_number} ha sido reactivada correctamente.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al reactivar cuenta', [
                'membership_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'messageError' => 'Ocurrió un error al procesar la reactivación. Intente de nuevo.',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
