<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Classes\Specialty;
use App\Models\Members\Member;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ClassController extends Controller
{
    public function getClubs($memberId)
    {
        try {
            $member = Member::find($memberId);

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Socio no encontrado',
                ], 500);
            }

            $accountMemberships = $member->accountMemberships()
                ->with(['membershipAccount.memberships' => function ($query) {
                    $query->where('is_primary', true)
                        ->whereIn('status', ['active', 'suspended'])
                        ->with('club');
                }])
                ->get();

            $clubs = [];
            foreach ($accountMemberships as $accountMember) {
                $memberships = $accountMember->membershipAccount->memberships;
                foreach ($memberships as $membership) {
                    $clubs[$membership->club_id] = $membership->club;
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($clubs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSpecialties(Request $request)
    {
        try {
            $query = Specialty::query();

            if ($request->filled('club_id')) {
                $query->where('club_id', $request->club_id);
            }

            $specialties = $query->get();

            return response()->json([
                'success' => true,
                'data' => $specialties,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
