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

class SendEmailNotificationJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MailService $mailService): void {
        $notification = Notification::query()
            ->with(['recipients', 'attachments'])
            ->find($this->notificationId);

        if ($notification && $notification->club_id) {
            foreach ($notification->recipients as $recipient) {
                $mailable = new EmailNotificationMailable(
                    subjectText: (string) $notification->subject,
                    titleText: (string) $notification->title,
                    bodyHtml: (string) $notification->body,
                    attachments: $notification->attachments->toArray()
                );

                $mailService->send(
                    entityId: (int) $notification->club_id,
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
        }
    }
}
