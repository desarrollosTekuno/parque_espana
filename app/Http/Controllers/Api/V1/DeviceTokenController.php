<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    /**
     * POST /api/v1/device-token
     *
     * Registra o actualiza el token FCM del dispositivo actual y suscribe
     * al topic de cada club al que pertenece el usuario.
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

        $user = $request->user();

        // Upsert: si el token ya existe lo reactiva y actualiza;
        // si no existe lo crea. Así un token nunca se duplica en la BD.
        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'      => $user->id,
                'platform'     => $validated['platform'],
                'device_name'  => $validated['device_name'] ?? null,
                'is_active'    => true,
                'last_seen_at' => now(),
            ]
        );

        // Suscribir al topic de cada club del usuario para recibir
        // notificaciones masivas (eventos, avisos, cierres, etc.)
        $this->firebase->subscribeTokenToUserClubs($validated['token'], $user->id);

        return response()->json(['success' => true], 200);
    }

    /**
     * DELETE /api/v1/device-token
     *
     * Desactiva el token del dispositivo y lo desuscribe de todos los topics.
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

        $user = $request->user();

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        // Desuscribir de los topics del club para no recibir más broadcasts
        $this->firebase->unsubscribeTokenFromUserClubs($validated['token'], $user->id);

        return response()->json(['success' => true], 200);
    }
}
