<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FamilyMembersController extends Controller
{
    /**
     * GET /api/v1/clubs/{club}/family-members
     *
     * Devuelve la lista de integrantes de la cuenta familiar del socio titular.
     *
     * Restricciones:
     *  - Solo socios titulares (is_primary_holder = true)
     *  - Solo membresías que permiten múltiples integrantes (allows_multiple_members = true)
     *  - Los dependientes reciben HTTP 403
     */
    public function index(Request $request, Club $club): JsonResponse
    {
        $user = $request->user();

        // Buscar la cuenta del usuario en este club donde sea titular
        $account = MembershipAccount::query()
            ->where('club_id', $club->id)
            ->whereHas('accountMembers', function ($q) use ($user) {
                $q->whereHas('member', fn($m) => $m->where('user_id', $user->id))
                  ->where('is_primary_holder', true);
            })
            ->with([
                // Membresía activa del club para verificar el tipo
                'memberships' => fn($q) => $q
                    ->where('club_id', $club->id)
                    ->where('status', 'active')
                    ->with('membershipType')
                    ->orderByDesc('is_primary'),

                // Todos los integrantes de la cuenta con su relación y datos personales
                'accountMembers' => fn($q) => $q
                    ->with([
                        'member:id,first_name,last_name,second_last_name,birthdate,photo_path',
                        'relationship:id,name',
                    ])
                    ->orderByDesc('is_primary_holder'), // titular primero
            ])
            ->first();

        // El usuario no tiene cuenta titular en este club
        if (!$account) {
            return response()->json([
                'message' => 'No tienes acceso a esta sección. Solo los socios titulares pueden ver los integrantes.',
            ], 403);
        }

        // Obtener la membresía activa y su tipo
        $membership     = $account->memberships->first();
        $membershipType = $membership?->membershipType;

        // Verificar que la membresía permita múltiples integrantes (familiar)
        if (!$membershipType || !$membershipType->allows_multiple_members) {
            return response()->json([
                'message' => 'Esta sección solo está disponible para membresías familiares.',
            ], 403);
        }

        // Construir la lista de integrantes
        $members = $account->accountMembers->map(function ($accountMember) {
            $member = $accountMember->member;

            return [
                'id'                => $member->id,
                'full_name'         => trim("{$member->first_name} {$member->last_name} {$member->second_last_name}"),
                'photo_url'         => $member->photo_path
                    ? Storage::url($member->photo_path)
                    : null,
                'birthdate'         => $member->birthdate,
                'age'               => $member->birthdate
                    ? \Carbon\Carbon::parse($member->birthdate)->age
                    : null,
                'relationship'      => $accountMember->relationship?->name ?? 'Titular',
                'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                'member_number'     => null, // ver nota abajo
            ];
        });

        return response()->json([
            'membership_number' => $account->membership_number,
            'membership_type'   => $membershipType->name,
            'active_members'    => $account->accountMembers->count(),
            'max_members'       => null, // configurable si se agrega al tipo de membresía
            'members'           => $members,
        ]);
    }
}
