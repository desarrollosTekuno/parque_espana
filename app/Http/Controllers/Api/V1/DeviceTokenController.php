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
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'       => ['required', 'string'],
            'platform'    => ['required', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

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

        $this->firebase->subscribeTokenToUserClubs($validated['token'], $user->id);

        return $this->success('Token de dispositivo registrado.');
    }

    /**
     * DELETE /api/v1/device-token
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

        $this->firebase->unsubscribeTokenFromUserClubs($validated['token'], $user->id);

        return $this->success('Token de dispositivo desactivado.');
    }
}
