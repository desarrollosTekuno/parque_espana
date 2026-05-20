<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * POST /api/v1/device-token
     *
     * Registra o actualiza el token FCM del dispositivo actual.
     * La app debe llamar esto al iniciar sesión y cada vez que FCM
     * notifique que el token fue renovado (onTokenRefresh en Flutter).
     *
     * Body:
     * {
     *   "token":       "fcm_token_...",
     *   "platform":    "android",          // "android" | "ios" | "web"
     *   "device_name": "Pixel 8 de Juan"   // opcional
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'       => ['required', 'string'],
            'platform'    => ['required', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Upsert: si el token ya existe lo reactiva y actualiza;
        // si no existe lo crea. Así un token nunca se duplica en la BD.
        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $validated['platform'],
                'device_name'  => $validated['device_name'] ?? null,
                'is_active'    => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true], 200);
    }

    /**
     * DELETE /api/v1/device-token
     *
     * Desactiva el token del dispositivo actual.
     * Llamar al hacer logout para que el usuario no siga recibiendo
     * notificaciones en ese dispositivo.
     *
     * Body:
     * {
     *   "token": "fcm_token_..."
     * }
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->update(['is_active' => false]);

        return response()->json(['success' => true], 200);
    }
}
