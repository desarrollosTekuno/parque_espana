<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberProfileController extends Controller
{
    /**
     * GET /api/v1/my-profile
     *
     * Retorna la información personal del socio autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        $member = Member::where('user_id', $request->user()->id)
            ->with(['primaryAddress.country', 'primaryAddress.state', 'primaryAddress.city'])
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil de socio asociado a este usuario.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatMember($member),
        ]);
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
