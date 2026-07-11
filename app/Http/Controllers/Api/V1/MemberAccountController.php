<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Memberships\MembershipAccountMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MemberAccountController extends Controller {

    public function getAccountMembers(int $member): JsonResponse {
        try {
            $accountMember = MembershipAccountMember::where('member_id', $member)->first();

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio no tiene una cuenta de membresía.',
                ], 500);
            }

            if (!$accountMember->is_primary_holder) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_primary_holder' => false,
                        'members' => [],
                    ],
                ]);
            }

            $otherMembers = MembershipAccountMember::where('membership_account_id', $accountMember->membership_account_id)
                ->where('member_id', '!=', $member)
                ->with('member:id,first_name,last_name,second_last_name')
                ->get()
                ->map(function ($accountMember) {
                    return [
                        'id' => $accountMember->member->id,
                        'full_name' => trim("{$accountMember->member->first_name} {$accountMember->member->last_name} {$accountMember->member->second_last_name}"),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'is_primary_holder' => true,
                    'members' => $otherMembers,
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los integrantes de la cuenta.',
            ], 500);
        }
    }
}
