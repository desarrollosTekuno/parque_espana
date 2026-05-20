<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
            // 'device' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $user = $request->user();
        $allPermissions = $user->getAllPermissions();

        // Agrupar permisos por club (mobile_club_1 / mobile_club_2)
        $mobileContexts = ['mobile_club_1', 'mobile_club_2'];
        $permissionsByClub = [];
        foreach ($mobileContexts as $contextValue) {
            $clubPermissions = $allPermissions
                ->filter(fn($p) => $p->contexts->contains('value', $contextValue))
                ->pluck('name')
                ->values();

            if ($clubPermissions->isNotEmpty()) {
                $permissionsByClub[$contextValue == 'mobile_club_1' ? 'PE1' : 'PE2'] = $clubPermissions;
            }
        }

        $member = Member::where('user_id', $user->id)->first();
        $memberData = $this->buildMemberData($member, $permissionsByClub);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $user->createToken($request->email)->plainTextToken,
            'member' => $memberData,
        ]);
    }

    private function buildMemberData(?Member $member, array $permissionsByClub): ?array
    {
        if (!$member) {
            return null;
        }

        $accountMemberships = $member->accountMemberships()
            ->with([
                'membershipAccount.memberships' => fn($q) => $q
                    ->where('is_primary', true)
                    ->whereIn('status', ['active', 'suspended'])
                    ->with('club', 'membershipType'),
            ])
            ->get();

        $clubs = $accountMemberships->flatMap(function ($accountMember) use ($permissionsByClub) {
            $account = $accountMember->membershipAccount;

            return $account->memberships->map(fn($membership) => [
                'club_id' => $membership->club_id,
                'club_name' => $membership->club?->name,
                'club_code' => $membership->club?->code,
                'membership_account_id' => $account->id,
                'membership_number' => $account->membership_number,
                'membership_type' => $membership->membershipType?->name,
                'status' => $membership->status,
                'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                'permissions' => $permissionsByClub[$membership->club?->code] ?? [],
            ]);
        })->values();

        return [
            'id' => $member->id,
            'full_name' => $member->full_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'clubs' => $clubs,
        ];
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}