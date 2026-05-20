<?php

namespace App\Jobs;

use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de reintentos si FCM falla temporalmente.
     */
    public int $tries = 3;

    /**
     * Esperar antes de reintentar (segundos).
     */
    public int $backoff = 10;

    /**
     * @param int    $userId  ID del usuario que recibirá la notificación
     * @param string $title   Título visible en el dispositivo
     * @param string $body    Cuerpo del mensaje
     * @param array  $data    Payload extra para la app (tipo, IDs para deep linking, etc.)
     *
     * Ejemplo de $data para SPEI confirmado:
     * [
     *   'type'          => 'spei_paid',
     *   'spei_order_id' => '3',
     *   'payment_id'    => '45',
     *   'club_id'       => '1',
     * ]
     */
    public function __construct(
        public readonly int    $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array  $data = [],
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        $firebase->sendToUser($this->userId, $this->title, $this->body, $this->data);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendPushNotificationJob falló definitivamente', [
            'user_id' => $this->userId,
            'title'   => $this->title,
            'error'   => $exception->getMessage(),
        ]);
    }
}
