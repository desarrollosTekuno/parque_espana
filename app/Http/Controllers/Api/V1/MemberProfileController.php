<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberProfileController extends Controller
{
    /**
     * GET /api/v1/my-profile
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'club_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $member = $this->memberForUser($request);

        if (!$member) {
            return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');
        }

        $data = $this->formatMember($member);

        if ($request->filled('club_id')) {
            $data['club_membership'] = $this->getMembershipForClub($member, (int) $request->club_id);
        }

        return $this->ok($data);
    }

    /**
     * PUT /api/v1/my-profile
     *
     * Actualiza los datos personales editables desde la app: nombre y correo
     * (identidad de acceso, tabla users). El resto de los datos del socio
     * (teléfono, domicilio, etc.) no se editan desde aquí.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->forceFill($validated)->save();

        $member = $this->memberForUser($request);
        if ($member) {
            $member->forceFill(['email' => $validated['email']])->save();
        }

        return $this->success('Datos actualizados correctamente.', $member ? $this->formatMember($member) : null);
    }

    /**
     * POST /api/v1/my-profile/photo
     *
     * Sube la foto de perfil a Digital Ocean Spaces y actualiza
     * users.profile_photo_path.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();
        $previousPath = $user->profile_photo_path;

        $path = $request->file('photo')->store('profile-photos', 'spaces');

        $user->forceFill(['profile_photo_path' => $path])->save();

        if ($previousPath) {
            Storage::disk('spaces')->delete($previousPath);
        }

        return $this->success('Foto de perfil actualizada correctamente.', [
            'photo_url' => Storage::disk('spaces')->temporaryUrl($path, now()->addMinutes(30)),
        ]);
    }

    /**
     * POST /api/v1/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->unprocessable('La contraseña actual es incorrecta.');
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        // Cierra el resto de sesiones activas, conservando la actual.
        $user->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return $this->success('Contraseña actualizada correctamente.');
    }

    private function memberForUser(Request $request): ?Member
    {
        return Member::where('user_id', $request->user()->id)
            ->with(['primaryAddress.country', 'primaryAddress.state', 'primaryAddress.city'])
            ->first();
    }

    private function getMembershipForClub(Member $member, int $clubId): ?array
    {
        $accountMember = $member->accountMemberships()
            ->with([
                'membershipAccount.memberships' => fn ($q) => $q
                    ->where('club_id', $clubId)
                    ->where('is_primary', true)
                    ->whereIn('status', ['active', 'suspended'])
                    ->with('club', 'membershipType'),
            ])
            ->whereHas('membershipAccount.memberships', fn ($q) => $q
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$accountMember) return null;

        $account    = $accountMember->membershipAccount;
        $membership = $account->memberships->first();

        if (!$membership) return null;

        // Un socio con membresía activa en ambos parques (paquete
        // interclub) paga directo en caja — la app no debe ofrecer pagar en
        // línea para esta cuenta (ver ChargePaymentController/
        // SpeiPaymentController, que además lo rechazan del lado del
        // servidor si de todos modos se intenta).
        $spansMultipleClubs = $account->spansMultipleClubs();

        return [
            'club_id'               => $membership->club_id,
            'club_name'             => $membership->club?->name,
            'club_code'             => $membership->club?->code,
            'membership_account_id' => $account->id,
            'membership_number'     => $account->membership_number,
            'account_type'          => $account->account_type,
            'account_status'        => $account->status,
            'membership_type'       => $membership->membershipType?->name,
            'membership_status'     => $membership->status,
            'is_primary_holder'     => (bool) $accountMember->is_primary_holder,
            'start_date'            => $membership->start_date,
            'end_date'              => $membership->end_date,
            'spans_multiple_clubs'  => $spansMultipleClubs,
            'can_pay_online'        => !$spansMultipleClubs,
        ];
    }

    private function formatMember(Member $member): array
    {
        $address = $member->primaryAddress;
        $user    = $member->user;

        return [
            'id'               => $member->id,
            // full_name/email reflejan la identidad de acceso (tabla users)
            // cuando existe, para que los cambios hechos desde "editar datos
            // personales" en la app se vean reflejados aquí.
            'full_name'        => $user?->name ?? $member->full_name,
            'first_name'       => $member->first_name,
            'last_name'        => $member->last_name,
            'second_last_name' => $member->second_last_name,
            'email'            => $user?->email ?? $member->email,
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
        // La foto subida desde "modificar foto de perfil" (users.profile_photo_path)
        // tiene prioridad sobre la foto capturada en el expediente del socio.
        $path = $member->user?->profile_photo_path ?? $member->photo_path;

        if (!$path) return null;

        return Storage::disk('spaces')->temporaryUrl(
            $path,
            now()->addMinutes(30)
        );
    }
}
