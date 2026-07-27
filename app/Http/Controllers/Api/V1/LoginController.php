<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Members\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->error('Credenciales incorrectas.', 401);
        }

        $user           = $request->user();
        $allPermissions = $user->getAllPermissions()->load('contexts');

        $mobileContexts   = ['mobile_club_1', 'mobile_club_2'];
        $permissionsByClub = [];
        foreach ($mobileContexts as $contextValue) {
            $clubPermissions = $allPermissions
                ->filter(fn ($p) => $p->contexts->contains('value', $contextValue))
                ->pluck('name')
                ->values();

            if ($clubPermissions->isNotEmpty()) {
                $permissionsByClub[$contextValue === 'mobile_club_1' ? 'PE1' : 'PE2'] = $clubPermissions;
            }
        }

        $member     = Member::where('user_id', $user->id)->first();
        $memberData = $this->buildMemberData($member, $permissionsByClub);

        return $this->success('Inicio de sesión exitoso.', [
            'token'  => $user->createToken($request->email)->plainTextToken,
            'member' => $memberData,
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $fcmToken = $request->input('fcm_token');
            if ($fcmToken) {
                DeviceToken::where('token', $fcmToken)
                    ->where('user_id', $request->user()->id)
                    ->update(['is_active' => false]);
            }

            $request->user()->currentAccessToken()->delete();

            return $this->success('Sesión cerrada correctamente.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('No se pudo cerrar la sesión.');
        }
    }

    private function buildMemberData(?Member $member, array $permissionsByClub): ?array
    {
        if (!$member) {
            return null;
        }

        $accountMemberships = $member->accountMemberships()
            ->with([
                'membershipAccount.memberships' => fn ($q) => $q
                    ->where('is_primary', true)
                    ->whereIn('status', ['active', 'suspended'])
                    ->with('club', 'membershipType'),
            ])
            ->get();

        $clubs = $accountMemberships->flatMap(function ($accountMember) use ($permissionsByClub) {
            $account = $accountMember->membershipAccount;

            return $account->memberships->map(fn ($membership) => [
                'club_id'               => $membership->club_id,
                'club_name'             => $membership->club?->name,
                'club_code'             => $membership->club?->code,
                'membership_account_id' => $account->id,
                'membership_number'     => $account->membership_number,
                'membership_type'       => $membership->membershipType?->name,
                'status'                => $membership->status,
                'is_primary_holder'     => (bool) $accountMember->is_primary_holder,
                'permissions'           => $permissionsByClub[$membership->club?->code] ?? [],
            ]);
        })->values();

        return [
            'id'        => $member->id,
            'full_name' => $member->full_name,
            'email'     => $member->email,
            'phone'     => $member->phone,
            'clubs'     => $clubs,
        ];
    }
}
