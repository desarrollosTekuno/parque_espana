<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccountMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberProfileController extends Controller
{
    /**
     * GET /api/v1/my-profile
     * GET /api/v1/my-profile?club_id=1
     *
     * Retorna la información personal del socio autenticado.
     * Si se envía `club_id`, incluye además los datos de membresía
     * correspondientes a ese parque.
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'club_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $member = Member::where('user_id', $request->user()->id)
            ->with(['primaryAddress.country', 'primaryAddress.state', 'primaryAddress.city'])
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil de socio asociado a este usuario.',
            ], 404);
        }

        $data = $this->formatMember($member);

        if ($request->filled('club_id')) {
            $data['club_membership'] = $this->getMembershipForClub($member, (int) $request->club_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Obtiene los datos de membresía del socio para un parque específico.
     * Retorna null si el socio no tiene membresía activa/suspendida en ese club.
     */
    private function getMembershipForClub(Member $member, int $clubId): ?array
    {
        $accountMember = $member->accountMemberships()
            ->with([
                'membershipAccount.memberships' => fn($q) => $q
                    ->where('club_id', $clubId)
                    ->where('is_primary', true)
                    ->whereIn('status', ['active', 'suspended'])
                    ->with('club', 'membershipType'),
            ])
            ->whereHas('membershipAccount.memberships', fn($q) => $q
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$accountMember) {
            return null;
        }

        $account    = $accountMember->membershipAccount;
        $membership = $account->memberships->first();

        if (!$membership) {
            return null;
        }

        return [
            'club_id'               => $membership->club_id,
            'club_name'             => $membership->club?->name,
            'club_code'             => $membership->club?->code,
            'membership_account_id' => $account->id,
            'membership_number'     => $account->membership_number,
            'account_type'          => $account->account_type,
            'account_status'        => $account->status,
            'membership_type'       => $membership->membershipType?->name,
            'membership_status'     => $membership->status,
            'is_primary_holder'     => (bool) $accountMember->is_primary_holder,
            'start_date'            => $membership->start_date,
            'end_date'              => $membership->end_date,
        ];
    }

    private function formatMember(Member $member): array
    {
        $address = $member->primaryAddress;

        return [
            'id'               => $member->id,
            'full_name'        => $member->full_name,
            'first_name'       => $member->first_name,
            'last_name'        => $member->last_name,
            'second_last_name' => $member->second_last_name,
            'email'            => $member->email,
            'phone'            => $member->phone,
            'birthdate'        => $member->birthdate,
            'age'              => $member->age,
            'photo_url'        => $this->resolvePhotoUrl($member),
            'address'          => $address ? [
                'street'       => $address->street,
                'neighborhood' => $address->neighborhood,
                'postal_code'  => $address->postal_code,
                'city'         => $address->city,
                'state'        => $address->state,
                'country'      => $address->country,
            ] : null,
        ];
    }

    private function resolvePhotoUrl(Member $member): ?string
    {
        if (!$member->photo_path) {
            return null;
        }

        return Storage::disk('spaces')->temporaryUrl(
            $member->photo_path,
            now()->addMinutes(30)
        );
    }
}
