<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Members\Locker;
use Illuminate\Support\Facades\Auth;

use App\Models\Memberships\Membership;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class LockerApiController extends Controller {

    public function index(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'category' => 'required|string',
            'club_id' => 'required|integer',
        ],[
            'account_id.required' => 'La cuenta es obligatoria',
            'club_id.required' => 'El club es obligatorio',
            'category.required' => 'Debes seleccionar una categoría',

            'account_id.integer' => 'La cuenta no es válida',
            'club_id.integer' => 'El club no es válido',
            'category.string' => 'La categoría no es válida',
        ]);

        if (!$request->club_id) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        // validar membresía en ese club
        $membership = Membership::where('membership_account_id', $request->account_id)
            ->where('club_id', $request->club_id)
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'No perteneces a este club'
            ], 403);
        }

        $lockers = Locker::query()
            ->where('club_id', $request->club_id)
            ->where('category', $request->category)
            ->where('status', 'disponible')
            ->orderBy('number')
            ->get();

        return response()->json($lockers);
    }

    public function membersAvailable(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'club_id' => 'required|integer',
        ]);

        $membership = Membership::where('membership_account_id', $request->account_id)
            ->where('club_id', $request->club_id)
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'Esta cuenta no pertenece a este club'
            ], 403);
        }

        $members = MembershipAccount::findOrFail($request->account_id)
            ->members()
            ->whereDoesntHave('lockerAssignment', function ($q) {
                $q->where('year', now()->year);
            })
            ->get([
                'members.id',
                'members.first_name',
                'members.last_name',
                'members.second_last_name',
            ]);

        return response()->json(
            $members->map(fn ($m) => [
                'label' => "{$m->first_name} {$m->last_name}",
                'value' => $m->id,
            ])
        );
    }
    public function assign(Request $request)
    {
        $request->validate([
            'locker_id' => 'required|integer',
            'member_id' => 'required|integer',
            'membership_account_id' => 'required|integer',
            'club_id' => 'required|integer',
            'category' => 'required|string',
        ],[
            'locker_id.required' => 'Debes seleccionar un casillero',
            'member_id.required' => 'Debes seleccionar un integrante',
            'account_id.required' => 'La cuenta es obligatoria',
            'club_id.required' => 'El club es obligatorio',
            'category.required' => 'Debes seleccionar una categoría',

            'locker_id.integer' => 'El casillero no es válido',
            'member_id.integer' => 'El integrante no es válido',
            'account_id.integer' => 'La cuenta no es válida',
            'club_id.integer' => 'El club no es válido',
            'category.string' => 'La categoría no es válida',
        ]);
        $clubId = $request->club_id;
        return DB::transaction(function () use ($request, $clubId) {

            // validar membresía en club
            $membership = Membership::where('membership_account_id', $request->membership_account_id)
                ->where('club_id', $clubId)
                ->first();

            if (!$membership) {
                return response()->json([
                    'message' => 'No perteneces a este club'
                ], 403);
            }

            // validar si ya tiene casillero
           /* $existing = LockerAssignment::where('member_id', $request->member_id)
                ->where('year', now()->year)
                ->exists();

            if ($existing) {
                return response()->json([
                    'message' => 'Este miembro ya tiene un casillero asignado'
                ], 422);
            }*/

            // bloquear casillero
            $locker = Locker::where('id', $request->locker_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locker->status !== 'disponible') {
                return response()->json([
                    'message' => 'El casillero ya no está disponible'
                ], 409);
            }

            // cálculo proporcional
            $annualCost = 1100;
            $month = now()->month;
            $monthsRemaining = 12 - $month + 1;
            $amount = round(($annualCost / 12) * $monthsRemaining, 2);

            // asignar
            LockerAssignment::create([
                'locker_id' => $locker->id,
                'member_id' => $request->member_id,
                'start_date' => now(),
                'end_date' => now()->endOfYear(),
                'amount_paid' => $amount,
                'year' => now()->year,
            ]);

            $locker->update(['status' => 'ocupado']);

            return response()->json([
                'message' => 'Casillero asignado correctamente',
                'amount' => $amount
            ]);
        });
    }
}
