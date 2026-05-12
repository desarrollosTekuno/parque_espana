<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Members\Locker;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Members\Member;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;

class LockerAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:members.lockers.create')->only('create');
        $this->middleware('permission:members.lockers.change')->only('change');
        $this->middleware('permission:members.lockers.remove')->only('remove');
        $this->middleware('permission:members.lockers.reserve')->only('reserve');
    }

    public function create($accountId)
    {   
        $account = MembershipAccount::with('members')->findOrFail($accountId);
        $pendingMembers = Charge::where('status', 'pending')
            ->where('period_year', now()->year)
            ->whereNotNull('metadata->locker_id')
            ->pluck('member_id')
            ->unique();

        $members = $account->members
            ->load('lockerAssignment')
            ->map(function ($member) use ($pendingMembers) {
                $hasLocker = $member->lockerAssignment !== null;
                $hasPendingLocker = $pendingMembers->contains($member->id);
                $member->has_pending_locker = $hasPendingLocker;
                $member->locked = $hasLocker || $hasPendingLocker;

                return $member;
        });
        $membership = Membership::where('membership_account_id', $accountId)->first();

        return Inertia::render('Members/Lockers/Create', [
            'membershipId' => $membership?->id,
            'accountId' => $accountId,
            'members' => $members,
            'clubId' => $membership?->club_id,
        ]);
    }

   
    public function reserve(Request $request)
    {
        $request->validate([
            'locker_id' => 'required|integer',
            'member_id' => 'required|integer',
        ]);

        return DB::transaction(function () use ($request) {

            $locker = Locker::where('id', $request->locker_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locker->status !== 'disponible' &&
                !($locker->status === 'pago_pendiente')
            ) {
                return back()->withErrors([
                    'locker' => 'El casillero ya no está disponible'
                ]);
            }

            // cálculo proporcional
            $annualCost = 1100;
            $month = now()->month;
            $monthsRemaining = 12 - $month + 1;
            $amount = round(($annualCost / 12) * $monthsRemaining, 2);

            // reservar
            $locker->update([
                'status' => 'pago_pendiente',
            ]);

            $concept = ChargeConcept::query()
                ->with('clubAmounts')
                ->where('code', 'LOCKERS')
                ->firstOrFail();

            Charge::create([
                'membership_account_id' => $request->account_id,
                'membership_id' => $request->membership_id ?? null,
                'member_id' => $request->member_id ?? null,
                'concept_id' => $concept->id,
                'description' => $concept->description,
                'amount' => $amount,
                'balance' => $amount,
                'issue_date' => now(),
                'due_date' => now()->addDays(7),
                'period_year' => now()->year,
                'period_month' => now()->month,
                'allows_partial_payments' => false,
                'status' => 'pending',
                'metadata' => [
                    'locker_id' => $locker->id,
                    'club_id' => $request->club_id,
                    'concept_amount_source' => 'club_or_default',
                ]
            ]);

            return redirect()
                ->route('members.lockers.create', $request->account_id)
                ->with('success', 'Casillero asignado correctamente');
        });
    }

    public function change(Request $request)
    {
        DB::transaction(function () use ($request) {

            $oldAssignment = LockerAssignment::where('locker_id', $request->old_locker_id)
                ->where('member_id', $request->member_id)
                ->whereNull('deleted_at')
                ->first();

            if ($oldAssignment) {

                $oldAssignment->delete();

                Locker::where('id', $request->old_locker_id)
                    ->update([
                        'status' => 'disponible'
                    ]);
            }

            LockerAssignment::create([
                'locker_id' => $request->new_locker_id,
                'member_id' => $request->member_id,
                'amount_paid' => 0,
                'start_date' => now(),
                'end_date' => now()->endOfYear(),
                'year' => now()->year,
            ]);

            Locker::where('id', $request->new_locker_id)
                ->update([
                    'status' => 'ocupado'
                ]);
        });

        return back();
    }
    public function remove(LockerAssignment $assignment)
    {
        try {
           /* if ($assignment->locker->club_id != 2) {

                return back()->withErrors([
                    'locker' => 'Solo Parque 2 puede dar de baja casilleros.'
                ]);
            }*/
            DB::transaction(function () use ($assignment) {
                $assignment->locker->update([
                    'status' => 'disponible'
                ]);
                $assignment->delete();
            });

            return back();
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'locker' => 'Error al dar de baja el casillero.'
            ]);
        }
    }
    
}