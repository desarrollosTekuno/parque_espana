<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Members\Member;
use App\Models\Members\ClinicalHistory;
use App\Http\Requests\ClinicalHistoryRequest;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ClinicalHistoryController extends Controller
{
    public function show(Request $request)
    {
       
        $history = ClinicalHistory::firstWhere( 
            'member_id',
            $request->member_id
        );

         if (!$history) {
            return response()->json([
                'success' => false,
                'message' => 'No existe información clínica para este miembro.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    public function upsert(ClinicalHistoryRequest $request)
    {
        $member = Member::find($request->member_id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'El miembro especificado no existe.',
                'data' => null
            ], 404);
        }
        $history = ClinicalHistory::updateOrCreate(
            ['member_id' => $request->member_id],
            $request->validated()
        );

        if (!$history) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar la historia clínica.',
                'data' => null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Historia clínica guardada correctamente.',
            'data' => $history
        ]);
    }
}
