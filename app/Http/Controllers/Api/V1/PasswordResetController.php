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
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Siempre respondemos igual para no revelar si el correo existe
        if (!$user) {
            return $this->success('Si el correo está registrado, recibirás un código en breve.');
        }

        $existing = PasswordResetOtp::find($request->email);
        if ($existing && $existing->created_at?->diffInMinutes(now()) < 2) {
            return $this->error('Ya enviamos un código recientemente. Espera un momento antes de solicitar otro.', 429);
        }

        $otp           = PasswordResetOtp::generate($request->email);
        $expiryMinutes = (int) config('auth.otp_expiry_minutes', 1440);

        Mail::to($user->email)->send(new PasswordResetOtpMail(
            otp:           $otp,
            userName:      $user->name,
            expiryMinutes: $expiryMinutes,
        ));

        return $this->success('Si el correo está registrado, recibirás un código en breve.');
    }

    /**
     * POST /api/v1/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'otp'      => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $record = PasswordResetOtp::find($request->email);

        if (!$record || !$record->isValid()) {
            return $this->unprocessable('El código es inválido o ha expirado. Solicita uno nuevo.');
        }

        if (!$record->verifyOtp($request->otp)) {
            return $this->unprocessable('El código ingresado es incorrecto.');
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->notFound('No se encontró una cuenta con ese correo.');
        }

        $user->update(['password' => Hash::make($request->password)]);

        $record->markAsUsed();
        $user->tokens()->delete();

        return $this->success('Contraseña actualizada correctamente. Inicia sesión con tu nueva contraseña.');
    }
}
