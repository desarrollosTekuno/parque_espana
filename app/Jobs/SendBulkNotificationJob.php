<?php

namespace App\Jobs;

use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationStatusCatalog;
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
        public readonly ?int $notificationId = null,
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        Log::info('SendBulkNotificationJob iniciado', [
            'type'   => $this->type,
            'target' => is_array($this->target) ? count($this->target) . ' usuarios' : $this->target,
            'title'  => $this->title,
        ]);

        try {
            match ($this->type) {
                // Envía a todos los socios de un club via FCM Topic (1 llamada)
                'club'  => $firebase->sendToClub((int) $this->target, $this->title, $this->body, $this->data),

                // Envía a un topic arbitrario (ej: 'club_1_titulares')
                'topic' => $firebase->sendToTopic((string) $this->target, $this->title, $this->body, $this->data),

                // Envía a una lista específica de user IDs en chunks de 500
                'users' => $firebase->sendToUsers((array) $this->target, $this->title, $this->body, $this->data),

                default => Log::warning('SendBulkNotificationJob: tipo desconocido', ['type' => $this->type]),
            };

            $this->saveNotificationLog('sent');
        } catch (\Throwable $e) {
            $this->saveNotificationLog('failed', $e->getMessage());
            throw $e;
        }
    }

    private function saveNotificationLog(string $status, ?string $errorMessage = null): void
    {
        if (!isset($this->notificationId) || !$this->notificationId) {
            return;
        }

        $deliveryLog = NotificationDeliveryLog::query()
            ->where('notification_id', $this->notificationId)
            ->where('channel', 'push')
            ->where('status', 'queued')
            ->latest('id')
            ->first();

        if ($deliveryLog) {
            $deliveryLog->update([
                'status' => $status,
                'sent_at' => $status === 'sent' ? now() : null,
                'error_message' => $errorMessage,
                'metadata' => [
                    'type' => $this->type,
                    'target' => is_array($this->target) ? count($this->target) : $this->target,
                ],
            ]);
        } else {
            NotificationDeliveryLog::create([
                'notification_id' => $this->notificationId,
                'channel' => 'push',
                'destination' => is_array($this->target) ? 'users' : (string) $this->target,
                'provider' => 'firebase',
                'status' => $status,
                'sent_at' => $status === 'sent' ? now() : null,
                'error_message' => $errorMessage,
                'metadata' => [
                    'type' => $this->type,
                    'target' => is_array($this->target) ? count($this->target) : $this->target,
                ],
            ]);
        }

        $statusCode = $status === 'sent' ? 'sent' : 'failed';
        $notificationStatus = NotificationStatusCatalog::query()->where('code', $statusCode)->first();

        Notification::query()
            ->where('id', $this->notificationId)
            ->update([
                'status_id' => $notificationStatus?->id,
                'sent_date' => $status === 'sent' ? now()->toDateString() : null,
                'sent_time' => $status === 'sent' ? now()->toTimeString() : null,
            ]);
    }
}
