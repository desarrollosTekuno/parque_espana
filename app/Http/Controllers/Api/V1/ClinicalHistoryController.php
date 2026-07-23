<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalHistoryRequest;
use App\Models\Members\ClinicalHistory;
use App\Models\Members\Member;
use Illuminate\Http\Request;

class ClinicalHistoryController extends Controller
{
    public function show(Request $request)
    {
        $history = ClinicalHistory::firstWhere('member_id', $request->member_id);

        if (!$history) {
            return $this->notFound('No existe información clínica para este miembro.');
        }

        return $this->ok($history);
    }

    public function upsert(ClinicalHistoryRequest $request)
    {
        $member = Member::find($request->member_id);

        if (!$member) {
            return $this->notFound('El miembro especificado no existe.');
        }

        $history = ClinicalHistory::updateOrCreate(
            ['member_id' => $request->member_id],
            $request->validated()
        );

        if (!$history) {
            return $this->serverError('No se pudo guardar la historia clínica.');
        }

        return $this->success('Historia clínica guardada correctamente.', $history);
    }
}
