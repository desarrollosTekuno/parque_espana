<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Administrator\Club;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MemberClubController extends Controller
{
    public function getMemberClubs(int $member): JsonResponse {
        try {
            $memberModel = Member::find($member);

            if (!$memberModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Socio no encontrado.',
                ], 500);
            }

            $accountIds = $memberModel->accountMemberships()->pluck('membership_account_id');

            $clubIds = Membership::whereIn('membership_account_id', $accountIds)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->pluck('club_id');

            $clubs = Club::whereIn('id', $clubIds)->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $clubs,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los clubes del socio.',
            ], 500);
        }
    }
}
