<?php

namespace App\Jobs;

use App\Mail\EmailNotificationMailable;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationStatusCatalog;
use App\Services\Email\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailNotificationJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId) {
    }

    public function handle(MailService $mailService): void {
        Log::info('SendEmailNotificationJob iniciado', [
            'notification_id' => $this->notificationId,
        ]);

        $notification = Notification::query()
            ->with(['recipients', 'attachments'])
            ->find($this->notificationId);

        if ($notification && $notification->club_id) {
            $hasFailedRecipients = false;

            foreach ($notification->recipients as $recipient) {
                if (!$recipient->destination) {
                    continue;
                }

                try {
                    $mailable = new EmailNotificationMailable(
                        subjectText: (string) $notification->title,
                        titleText: (string) $notification->title,
                        bodyHtml: (string) $notification->body,
                        files: $notification->attachments->toArray()
                    );

                    $mailService->send(
                        entityId: (int) $notification->club_id,
                        to: (string) $recipient->destination,
                        mailable: $mailable,
                        notificationId: $notification->id
                    );

                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    $hasFailedRecipients = true;

                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($e->getMessage(), 0, 65535),
                    ]);

                    Log::error('Error al enviar notificacion por correo', [
                        'notification_id' => $notification->id,
                        'recipient_id' => $recipient->id,
                        'destination' => $recipient->destination,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $statusCode = $hasFailedRecipients ? 'failed' : 'sent';
            $sentStatus = NotificationStatusCatalog::query()->where('code', $statusCode)->first();

            $notification->update([
                'status_id' => $sentStatus?->id ?? $notification->status_id,
                'sent_date' => now()->toDateString(),
                'sent_time' => now()->toTimeString(),
            ]);

            Log::info('SendEmailNotificationJob completado', [
                'notification_id' => $this->notificationId,
                'recipients_count' => $notification->recipients->count(),
            ]);
        } else {
            Log::warning('SendEmailNotificationJob sin notificacion valida', [
                'notification_id' => $this->notificationId,
            ]);
        }
    }
}
