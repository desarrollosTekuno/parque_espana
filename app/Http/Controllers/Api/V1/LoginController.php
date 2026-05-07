<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            ], 200);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 200);
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
                $permissionsByClub[$contextValue] = $clubPermissions;
            }
        }

        return response()->json([
            'success'            => true,
            'message'            => 'Login successful',
            'token'              => $user->createToken($request->email)->plainTextToken,
            'permissions_by_club' => $permissionsByClub,
        ]);
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