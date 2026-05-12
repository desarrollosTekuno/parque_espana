<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Http\Request;
use Throwable;

class FirebaseTestController extends Controller {

    public function send(Request $request, FirebaseNotificationService $firebaseNotificationService) {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:500'],
            'data' => ['nullable', 'array'],
        ]);

        try {
            $firebaseNotificationService->sendToToken(
                token: $validated['token'],
                title: $validated['title'],
                body: $validated['body'],
                data: $validated['data'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Notificacion enviada correctamente',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar la notificacion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
