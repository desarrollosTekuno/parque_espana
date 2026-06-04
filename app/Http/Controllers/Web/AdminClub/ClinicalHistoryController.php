<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Members\ClinicalHistory;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use Illuminate\Http\Request;
use App\Http\Requests\ClinicalHistoryRequest;

class ClinicalHistoryController extends Controller
{
    public function index(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership->loadMissing('account.accountMembers.member');

        $histories = $membership->account->accountMembers
            ->sortByDesc('is_primary_holder')
            ->map(function ($accountMember) {
                $member = $accountMember->member;
                if (!$member) {
                    return null;
                }

                $history = ClinicalHistory::where('member_id', $member->id)->first();

                return [
                    'member_id'        => $member->id,
                    'member_name'      => $member->full_name,
                    'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                    'history'          => $history ? $this->buildPayload($history) : null,
                ];
            })
            ->filter()
            ->values();

        return response()->json($histories);
    }

    public function upsert(ClinicalHistoryRequest $request, Membership $membership, Member $member)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership->loadMissing('account.accountMembers');
        $memberIds = $membership->account->accountMembers->pluck('member_id');

        if (!$memberIds->contains($member->id)) {
            abort(404);
        }

        ClinicalHistory::updateOrCreate(
            ['member_id' => $member->id],
            $request->validated()
        );

        return response()->json(['message' => 'Historia clínica guardada correctamente.']);
    }

    private function buildPayload(ClinicalHistory $history): array
    {
        return [
            'blood_type'               => $history->blood_type,
            'blood_rh'                 => $history->blood_rh,
            'has_diabetes'             => (bool) $history->has_diabetes,
            'diabetes_type'            => $history->diabetes_type,
            'has_heart_condition'      => (bool) $history->has_heart_condition,
            'has_epilepsy'             => (bool) $history->has_epilepsy,
            'has_asthma'               => (bool) $history->has_asthma,
            'has_allergy'              => (bool) $history->has_allergy,
            'takes_medication'         => (bool) $history->takes_medication,
            'medication_details'       => $history->medication_details,
            'has_allergens'            => (bool) $history->has_allergens,
            'allergen_details'         => $history->allergen_details,
            'normal_blood_pressure'    => $history->normal_blood_pressure !== null
                ? (bool) $history->normal_blood_pressure
                : null,
            'has_hypertension'         => (bool) $history->has_hypertension,
            'special_conditions'       => $history->special_conditions,
            'emergency_contact_name'   => $history->emergency_contact_name,
            'emergency_contact_phone'  => $history->emergency_contact_phone,
            'emergency_contact_mobile' => $history->emergency_contact_mobile,
            'emergency_notify_name'    => $history->emergency_notify_name,
            'treating_physician'       => $history->treating_physician,
            'treating_physician_phone' => $history->treating_physician_phone,
            'social_security_number'   => $history->social_security_number,
            'medical_insurance'        => $history->medical_insurance,
            'insurance_company'        => $history->insurance_company,
            'insurance_policy_number'  => $history->insurance_policy_number,
            'insurance_mobile'         => $history->insurance_mobile,
        ];
    }
}
