<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classes\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SpecialtyController extends Controller {

    public function getSpecialties(Request $request): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                'club_id' => ['required', 'integer'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 500);
            }

            $specialties = Specialty::where('club_id', $request->club_id)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

            return response()->json([
                'success' => true,
                'data' => $specialties,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener especialidades.',
            ], 500);
        }
    }
}
