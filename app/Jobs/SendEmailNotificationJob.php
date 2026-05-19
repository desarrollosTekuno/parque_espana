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
            ->with(['recipients.user.clubs:id', 'attachments'])
            ->find($this->notificationId);

        if ($notification && $notification->club_id) {
            foreach ($notification->recipients as $recipient) {
                $entityId = (int) $notification->club_id;

                if ($recipient->user && $recipient->user->clubs->isNotEmpty()) {
                    $entityId = (int) $recipient->user->clubs->first()->id;
                }

                if ($entityId <= 0) {
                    Log::warning('No se encontro parque para enviar correo', [
                        'notification_id' => $this->notificationId,
                        'recipient_id' => $recipient->id,
                    ]);
                    continue;
                }

                $mailable = new EmailNotificationMailable(
                    subjectText: (string) $notification->title,
                    titleText: (string) $notification->title,
                    bodyHtml: (string) $notification->body,
                    files: $notification->attachments->toArray()
                );

                $mailService->send(
                    entityId: $entityId,
                    to: (string) $recipient->destination,
                    mailable: $mailable
                );

                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            $sentStatus = NotificationStatusCatalog::query()->where('code', 'sent')->first();

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
