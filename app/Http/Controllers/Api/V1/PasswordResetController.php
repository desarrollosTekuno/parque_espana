<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\Auth\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    /**
     * POST /api/v1/forgot-password
     *
     * Genera un OTP de 6 dígitos y lo envía al correo del socio.
     *
     * Body: { "email": "socio@ejemplo.com" }
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Siempre respondemos igual para no revelar si el correo existe
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Si el correo está registrado, recibirás un código en breve.',
            ]);
        }

        // Throttle: evitar spam (máximo 1 OTP cada 2 minutos)
        $existing = PasswordResetOtp::find($request->email);
        if ($existing && $existing->created_at?->diffInMinutes(now()) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Ya enviamos un código recientemente. Espera un momento antes de solicitar otro.',
            ], 429);
        }

        $otp           = PasswordResetOtp::generate($request->email);
        $expiryMinutes = (int) config('auth.otp_expiry_minutes', 1440);

        Mail::to($user->email)->send(new PasswordResetOtpMail(
            otp:           $otp,
            userName:      $user->name,
            expiryMinutes: $expiryMinutes,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, recibirás un código en breve.',
        ]);
    }

    /**
     * POST /api/v1/reset-password
     *
     * Valida el OTP y establece la nueva contraseña.
     *
     * Body:
     * {
     *   "email":                 "socio@ejemplo.com",
     *   "otp":                   "123456",
     *   "password":              "nuevaContraseña",
     *   "password_confirmation": "nuevaContraseña"
     * }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'otp'      => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $record = PasswordResetOtp::find($request->email);

        // Verificar que el registro exista y sea válido
        if (!$record || !$record->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'El código es inválido o ha expirado. Solicita uno nuevo.',
            ], 422);
        }

        // Verificar que el OTP coincida
        if (!$record->verifyOtp($request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'El código ingresado es incorrecto.',
            ], 422);
        }

        // Actualizar la contraseña
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una cuenta con ese correo.',
            ], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Invalidar el OTP y revocar tokens activos de la app
        $record->markAsUsed();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente. Inicia sesión con tu nueva contraseña.',
        ]);
    }
}
