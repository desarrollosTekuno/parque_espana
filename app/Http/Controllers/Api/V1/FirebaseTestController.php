<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class FirebaseTestController extends Controller
{
    public function send(Request $request, FirebaseNotificationService $firebaseNotificationService): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'title' => ['required', 'string', 'max:150'],
            'body'  => ['required', 'string', 'max:500'],
            'data'  => ['nullable', 'array'],
        ]);

        try {
            $firebaseNotificationService->sendToToken(
                token: $validated['token'],
                title: $validated['title'],
                body:  $validated['body'],
                data:  $validated['data'] ?? [],
            );

            return $this->success('Notificación enviada correctamente.');
        } catch (Throwable $e) {
            report($e);
            return $this->serverError('No se pudo enviar la notificación.');
        }
    }

    public function ping(): JsonResponse
    {
        try {
            app('firebase.messaging');

            return $this->success('Firebase conectado correctamente.', [
                'project' => config('firebase.projects.app.credentials'),
            ]);
        } catch (Throwable $e) {
            report($e);
            return $this->serverError('Error conectando con Firebase.');
        }
    }
}
