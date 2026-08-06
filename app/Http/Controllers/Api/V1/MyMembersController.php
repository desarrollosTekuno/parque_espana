<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MyMembersController extends Controller
{
    /**
     * GET /api/v1/clubs/{club}/my-members
     *
     * Titular: devuelve todos los integrantes de su cuenta en el club.
     * No titular: devuelve únicamente su propio registro.
     */
    public function index(Request $request, Club $club): JsonResponse
    {
        $user = $request->user();

        $authMember = Member::where('user_id', $user->id)->first();
        if (!$authMember) {
            return $this->forbidden('No tienes una membresía activa en este club.');
        }

        // Buscar el account_member del usuario autenticado en este club
        $myAccountMember = MembershipAccountMember::query()
            ->where('member_id', $authMember->id)
            ->whereHas('membershipAccount', fn ($q) => $q->where('club_id', $club->id))
            ->with('membershipAccount')
            ->first();

        if (!$myAccountMember) {
            return $this->forbidden('No tienes una membresía activa en este club.');
        }

        // Titular: devolver todos los integrantes de la cuenta
        if ($myAccountMember->is_primary_holder) {
            $account = MembershipAccount::with([
                'accountMembers' => fn ($q) => $q
                    ->with(['member:id,first_name,last_name,second_last_name,photo_path'])
                    ->orderByDesc('is_primary_holder'),
            ])->find($myAccountMember->membership_account_id);

            $members = $account->accountMembers->map(fn ($am) => $this->formatMember($am));

            return $this->ok($members);
        }

        // No titular: solo su propio registro
        $myAccountMember->load('member:id,first_name,last_name,second_last_name,photo_path');

        return $this->ok([$this->formatMember($myAccountMember)]);
    }

    private function formatMember(MembershipAccountMember $accountMember): array
    {
        $member = $accountMember->member;

        return [
            'member_id'          => $member->id,
            'full_name'          => $member->full_name,
            'photo_url'          => $member->photo_path
                ? Storage::url($member->photo_path)
                : null,
            'access_code'        => $accountMember->access_code,
            'access_valid_until' => $accountMember->access_valid_until,
            'access_status'      => $accountMember->access_status ?? 'active',
        ];
    }
}
