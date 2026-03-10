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
        $permissions = $request->user()
            ->getAllPermissions()
            ->filter(function ($permission) {
                return $permission->contexts->contains('value', 'mobile_app');
            })
            ->values()
            ->pluck('name');
        return response()->json([
            // 'token' => $request->user()->createToken($request->device)->plainTextToken,
            // 'message' => 'Success'
            'success' => true,
            'message' => 'Login successful',
            'token' => $request->user()->createToken($request->email)->plainTextToken,
            'permissions' => $permissions
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