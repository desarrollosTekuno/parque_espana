<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use Illuminate\Http\JsonResponse;

class ReservableMemberController extends Controller {

    public function getReservableMembers(int $member): JsonResponse {
        try {
            $memberModel = Member::find($member);

            if (!$memberModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Socio no encontrado.',
                ], 500);
            }

            $accountMember = $memberModel->accountMemberships()->with('membershipAccount')->first();

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio no pertenece a ninguna cuenta de membresia.',
                ], 500);
            }

            $result = [];

            if ($accountMember->is_primary_holder) {
                $familyMembers = $accountMember->membershipAccount->accountMembers()->with('member', 'relationship')->get();

                foreach ($familyMembers as $familyMember) {
                    $result[] = [
                        'id'   => $familyMember->member->id,
                        'name' => $familyMember->member->full_name,
                        'role' => $familyMember->is_primary_holder ? 'titular' : ($familyMember->relationship->name ?? 'dependiente'),
                    ];
                }
            } else {
                $result[] = [
                    'id'   => $memberModel->id,
                    'name' => $memberModel->full_name,
                    'role' => 'dependiente',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los socios para reservar.',
            ], 500);
        }
    }
}
