<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;

class FirebaseService
{
    public function __construct(private Messaging $messaging) {}

    /**
     * Envía una notificación push a todos los dispositivos activos del usuario.
     *
     * @param  int    $userId
     * @param  string $title    Título de la notificación
     * @param  string $body     Cuerpo del mensaje
     * @param  array  $data     Datos adicionales para la app (navegación, IDs, etc.)
     * @return void
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)
            ->active()
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Envía una notificación a una lista de tokens FCM.
     * Elimina automáticamente los tokens que FCM reporte como inválidos.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens)) {
            return;
        }

        // Todos los valores del data payload deben ser strings
        $stringData = collect($data)
            ->map(fn($v) => (string) $v)
            ->toArray();

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($stringData);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            // Limpiar tokens inválidos reportados por FCM
            if ($report->hasFailures()) {
                $invalidTokens = collect($report->failures()->getItems())
                    ->map(fn($failure) => $failure->target()->value())
                    ->filter()
                    ->toArray();

                if (!empty($invalidTokens)) {
                    $this->deactivateTokens($invalidTokens);

                    Log::info('Tokens FCM inválidos desactivados', [
                        'count'  => count($invalidTokens),
                    ]);
                }
            }

        } catch (MessagingException $e) {
            Log::error('Error enviando notificación FCM', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Marca tokens como inactivos (FCM los reportó como inválidos o expirados).
     */
    private function deactivateTokens(array $tokens): void
    {
        DeviceToken::whereIn('token', $tokens)->update(['is_active' => false]);
    }
}
