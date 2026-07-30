<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Billing\Charge;
use App\Models\Members\Locker;
use App\Models\Billing\ChargeConcept;
use App\Models\Memberships\Membership;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\MembershipAccount;

class LockerApiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'category'   => 'required|string',
            'club_id'    => 'required|integer',
        ], [
            'account_id.required' => 'La cuenta es obligatoria',
            'club_id.required'    => 'El club es obligatorio',
            'category.required'   => 'Debes seleccionar una categoría',
            'account_id.integer'  => 'La cuenta no es válida',
            'club_id.integer'     => 'El club no es válido',
            'category.string'     => 'La categoría no es válida',
        ]);

        $membership = Membership::where('membership_account_id', $request->account_id)
            ->where('club_id', $request->club_id)
            ->first();

        if (!$membership) {
            return $this->forbidden('No perteneces a este club.');
        }

        $lockers = Locker::query()
            ->where('club_id', $request->club_id)
            ->where('category', $request->category)
            ->where('status', 'disponible')
            ->orderBy('number')
            ->simplePaginate(20);

        return $this->ok([
            'items' => $lockers->items(),
            'meta'  => [
                'current_page'   => $lockers->currentPage(),
                'per_page'       => $lockers->perPage(),
                'has_more_pages' => $lockers->hasMorePages(),
            ],
        ]);
    }

    public function membersAvailable(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'club_id'    => 'required|integer',
        ]);

        $membership = Membership::where('membership_account_id', $request->account_id)
            ->where('club_id', $request->club_id)
            ->first();

        if (!$membership) {
            return $this->forbidden('Esta cuenta no pertenece a este club.');
        }

        $members = MembershipAccount::findOrFail($request->account_id)
            ->members()
            ->get(['members.id', 'members.first_name', 'members.last_name', 'members.second_last_name']);

        return $this->ok($members->map(fn ($m) => [
            'label' => "{$m->first_name} {$m->last_name}",
            'value' => $m->id,
        ]));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'locker_id'             => 'required|integer',
            'member_id'             => 'required|integer',
            'membership_account_id' => 'required|integer',
            'club_id'               => 'required|integer',
            'category'              => 'required|string',
        ], [
            'locker_id.required'             => 'Debes seleccionar un casillero',
            'member_id.required'             => 'Debes seleccionar un integrante',
            'club_id.required'               => 'El club es obligatorio',
            'category.required'              => 'Debes seleccionar una categoría',
            'locker_id.integer'              => 'El casillero no es válido',
            'member_id.integer'              => 'El integrante no es válido',
            'club_id.integer'                => 'El club no es válido',
            'membership_account_id.required' => 'La cuenta es obligatoria',
            'category.string'                => 'La categoría no es válida',
        ]);

        $clubId = $request->club_id;

        return DB::transaction(function () use ($request, $clubId) {
            $membership = Membership::where('membership_account_id', $request->membership_account_id)
                ->where('club_id', $clubId)
                ->first();

            if (!$membership) {
                return $this->forbidden('No perteneces a este club.');
            }

            $locker = Locker::where('id', $request->locker_id)->lockForUpdate()->firstOrFail();

            if ($locker->status !== 'disponible') {
                return $this->conflict('El casillero ya no está disponible.');
            }

            [$concept, $annualCost, $amount, ] = $this->resolveLockerPricing($clubId);

            LockerAssignment::create([
                'locker_id'  => $locker->id,
                'member_id'  => $request->member_id,
                'start_date' => now(),
                'end_date'   => now()->endOfYear(),
                'amount_paid' => $amount,
                'year'       => now()->year,
                'club_id' => $clubId,
            ]);

            Charge::create([
                'membership_account_id' => $request->membership_account_id,
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
                    'club_id' => $clubId,
                    'concept_amount_source' => 'club_or_default',
                ]
            ]);

            $locker->update(['status' => 'ocupado']);

            return $this->success('Casillero asignado correctamente.', ['amount' => $amount]);
        });
    }
    public function pricing(Request $request)
    {
        $request->validate([
            'club_id' => 'required|integer',
        ], [
            'club_id.required' => 'El club es obligatorio',
            'club_id.integer'  => 'El club no es válido',
        ]);

        [, $annualAmount, $proratedAmount, $monthsRemaining] = $this->resolveLockerPricing($request->club_id);

        return $this->ok([
            'annual_amount'    => $annualAmount,
            'prorated_amount'  => $proratedAmount,
            'months_remaining' => $monthsRemaining,
        ]);
    }

    public function mine(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'club_id'    => 'required|integer',
        ], [
            'account_id.required' => 'La cuenta es obligatoria',
            'club_id.required'    => 'El club es obligatorio',
        ]);

        $membership = Membership::where('membership_account_id', $request->account_id)
            ->where('club_id', $request->club_id)
            ->first();

        if (!$membership) {
            return $this->forbidden('No perteneces a este club.');
        }

        $assignments = LockerAssignment::with(['locker', 'member'])
            ->whereHas('member', function ($q) use ($request) {
                $q->whereIn('id', function ($sub) use ($request) {
                    $sub->select('member_id')
                        ->from('memberships.account_members')
                        ->where('membership_account_id', $request->account_id);
                });
            })
            ->whereHas('locker', function ($q) use ($request) {
                $q->where('club_id', $request->club_id);
            })
            ->orderByDesc('year')
            ->get();

        return $this->ok($assignments->map(fn ($assignment) => [
            'id'          => $assignment->id,
            'year'        => $assignment->year,
            'start_date'  => $assignment->start_date,
            'end_date'    => $assignment->end_date,
            'amount_paid' => (float) $assignment->amount_paid,
            'locker'      => [
                'id'       => $assignment->locker->id,
                'number'   => $assignment->locker->number,
                'category' => $assignment->locker->category,
            ],
            'member' => [
                'id'    => $assignment->member->id,
                'label' => trim("{$assignment->member->first_name} {$assignment->member->last_name}"),
            ],
        ]));
    }

    /**
     * Resuelve el precio del concepto "LOCKERS" para un club: [concept, annualAmount, proratedAmount, monthsRemaining].
     * El prorrateo cuenta el mes actual como completo (meses restantes hasta diciembre).
     */
    private function resolveLockerPricing(int $clubId): array
    {
        $concept = ChargeConcept::query()
            ->with('clubAmounts')
            ->where('code', 'LOCKERS')
            ->firstOrFail();

        $annualAmount = $concept->resolveAmountForClub($clubId) ?? 0.0;
        $monthsRemaining = 12 - now()->month + 1;
        $proratedAmount = round(($annualAmount / 12) * $monthsRemaining, 2);

        return [$concept, $annualAmount, $proratedAmount, $monthsRemaining];
    }
}
