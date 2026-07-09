<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Classes\Specialty;
use App\Models\Members\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialtyController extends Controller {

    public function list(Request $request, Club $club): JsonResponse {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $hasMembership = $member->accountMemberships()
                ->whereHas('membershipAccount.memberships', function ($query) use ($club) {
                    $query->where('club_id', $club->id)
                        ->whereIn('status', ['active', 'suspended']);
                })
                ->exists();

            if (!$hasMembership) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este club.',
                ], 403);
            }

            $specialties = Specialty::where('club_id', $club->id)
                ->orderBy('name')
                ->get()
                ->map(fn ($specialty) => [
                    'id' => $specialty->id,
                    'name' => $specialty->name,
                    'code' => $specialty->code,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Especialidades obtenidas correctamente.',
                'data' => $specialties,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener especialidades.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
