<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Members\Locker;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Members\Member;
use App\Models\Members\LockerAssignment;
use App\Models\Members\LockerAssignmentHistory;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $concept = ChargeConcept::query()
            ->with('clubAmounts')
            ->where('code', 'LOCKERS')
            ->first();
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
            'anualCost' => $concept->default_amount,
        ]);
    }

   
    public function reserve(Request $request)
    {
        $request->validate([
            'locker_id' => 'required|integer',
            'member_id' => 'required|integer',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        return DB::transaction(function () use ($request) {

            $locker = Locker::where('id', $request->locker_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locker->status !== 'disponible') {
                return back()->withErrors([
                    'locker' => 'El casillero ya no está disponible'
                ]);
            }

            $locker->update([
                'status' => 'ocupado',
            ]);

            // SUBIDA ARCHIVO
            $file = $request->file('file');

            $directory = "locker_assignments/{$request->member_id}";
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            $uploaded = Storage::disk('spaces')->putFileAs(
                $directory,
                $file,
                $filename
            );

            if ($uploaded === false) {
                throw new \RuntimeException(
                    'No se pudo subir el comprobante del casillero.'
                );
            }

            $path = "{$directory}/{$filename}";

            LockerAssignment::create([
                'locker_id' => $locker->id,
                'club_id' => session('club_id'),
                'member_id' => $request->member_id,
                'amount_paid' => 0,
                'start_date' => now(),
                'end_date' => now()->endOfYear(),
                'year' => now()->year,
                'file_path' => $path,
            ]);

            $concept = ChargeConcept::query()
                ->with('clubAmounts')
                ->where('code', 'LOCKERS')
                ->firstOrFail();

            $annualCost = $concept->clubAmounts
                ->where('club_id', session('club_id'))
                ->first()
                ?->amount ?? $concept->default_amount;

            $month = now()->month;
            $monthsRemaining = 12 - $month + 1;

            $amount = round(
                ($annualCost / 12) * $monthsRemaining,
                2
            );

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
                    'club_id' => session('club_id'),
                    'concept_amount_source' => 'club_or_default',
                ]
            ]);

            return redirect()
                ->route('members.lockers.create', $request->account_id)
                ->with(
                    'success',
                    'Casillero asignado correctamente'
                );
        });
    }

    public function change(Request $request)
    {
        $request->validate([
            'member_id' => 'required|integer',
            'old_locker_id' => 'required|integer',
            'new_locker_id' => 'required|integer',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::transaction(function () use ($request) {

            $assignment = LockerAssignment::where('member_id', $request->member_id)
                ->where('locker_id', $request->old_locker_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$assignment) {
                return;
            }

            $oldLockerId = $assignment->locker_id;

            // liberar locker anterior
            Locker::where('id', $oldLockerId)
                ->update([
                    'status' => 'disponible'
                ]);

            // ocupar nuevo locker
            Locker::where('id', $request->new_locker_id)
                ->update([
                    'status' => 'ocupado'
                ]);

            $path = null;

            // SUBIR ARCHIVO
            if ($request->hasFile('file')) {

                $file = $request->file('file');

                $directory = "locker_assignments/{$request->member_id}";
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                $uploaded = Storage::disk('spaces')->putFileAs(
                    $directory,
                    $file,
                    $filename
                );
    
                if ($uploaded === false) {
                    throw new \RuntimeException(
                        'No se pudo subir el comprobante del cambio de casillero.'
                    );
                }
                $path = "{$directory}/{$filename}";
                //Storage::disk('spaces')->setVisibility($path, 'public');
                
            }

            // guardar historial
            LockerAssignmentHistory::create([
                'locker_assignment_id' => $assignment->id,
                'member_id' => $request->member_id,
                'old_locker_id' => $oldLockerId,
                'new_locker_id' => $request->new_locker_id,
                'changed_at' => now(),
                'changed_by' => Auth::id(),
                'file_path' => $path,
            ]);

            // actualizar asignación actual
            $assignment->update([
                'locker_id' => $request->new_locker_id,
            ]);
        });

        return back()->with(
            'success',
            'Casillero cambiado correctamente.'
        );
    }
    public function remove(LockerAssignment $assignment)
    {
        try {
            DB::transaction(function () use ($assignment) {
                $assignment->locker->update([
                    'status' => 'disponible'
                ]);

                $assignment->update([
                    'cancellation_reason' => 'Baja voluntaria'
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