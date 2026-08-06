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
     */
    public function index(Request $request, Club $club): JsonResponse
    {
        $user = $request->user();

        $account = MembershipAccount::query()
            ->where('club_id', $club->id)
            ->whereHas('accountMembers', function ($q) use ($user) {
                $q->whereHas('member', fn ($m) => $m->where('user_id', $user->id))
                  ->where('is_primary_holder', true);
            })
            ->with([
                'memberships' => fn ($q) => $q
                    ->where('club_id', $club->id)
                    ->where('status', 'active')
                    ->with('membershipType')
                    ->orderByDesc('is_primary'),
                'accountMembers' => fn ($q) => $q
                    ->with([
                        'member:id,first_name,last_name,second_last_name,birthdate,photo_path',
                        'relationship:id,name',
                    ])
                    ->orderByDesc('is_primary_holder'),
            ])
            ->first();

        if (!$account) {
            return $this->forbidden('No tienes acceso a esta sección. Solo los socios titulares pueden ver los integrantes.');
        }

        $membership     = $account->memberships->first();
        $membershipType = $membership?->membershipType;

        if (!$membershipType || !$membershipType->allows_multiple_members) {
            return $this->forbidden('Esta sección solo está disponible para membresías familiares.');
        }

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
                'member_number'     => null,
            ];
        });

        return $this->ok([
            'membership_number' => $account->membership_number,
            'membership_type'   => $membershipType->name,
            'active_members'    => $account->accountMembers->count(),
            'max_members'       => null,
            'members'           => $members,
        ]);
    }
}
