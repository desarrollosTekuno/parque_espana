<?php

namespace App\Jobs;

use App\Services\FirebaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBulkNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 30; // segundos entre reintentos (más tiempo por volumen)

    /**
     * @param  string     $type    'club' | 'topic' | 'users'
     * @param  int|string|array $target  club_id, topic name, o array de user_ids
     * @param  string     $title
     * @param  string     $body
     * @param  array      $data    Payload para deep linking en Flutter
     */
    public function __construct(
        public readonly string $type,
        public readonly int|string|array $target,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        Log::info('SendBulkNotificationJob iniciado', [
            'type'   => $this->type,
            'target' => is_array($this->target) ? count($this->target) . ' usuarios' : $this->target,
            'title'  => $this->title,
        ]);

        match ($this->type) {
            // Envía a todos los socios de un club via FCM Topic (1 llamada)
            'club'  => $firebase->sendToClub((int) $this->target, $this->title, $this->body, $this->data),

            // Envía a un topic arbitrario (ej: 'club_1_titulares')
            'topic' => $firebase->sendToTopic((string) $this->target, $this->title, $this->body, $this->data),

            // Envía a una lista específica de user IDs en chunks de 500
            'users' => $firebase->sendToUsers((array) $this->target, $this->title, $this->body, $this->data),

            default => Log::warning('SendBulkNotificationJob: tipo desconocido', ['type' => $this->type]),
        };
    }
}
