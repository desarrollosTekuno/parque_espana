<?php

namespace App\Jobs;

use App\Mail\WebsiteContactMessageMailable;
use App\Models\AdminClub\SystemVariable;
use App\Models\Website\ContactMessage;
use App\Services\Email\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWebsiteContactMessageJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public int $contactMessageId) {
    }

    public function handle(MailService $mailService): void {
        $contactMessage = ContactMessage::query()
            ->with('club')
            ->find($this->contactMessageId);

        if (!$contactMessage || !$contactMessage->club) {
            Log::warning('Mensaje de contacto sin parque asociado', [
                'contact_message_id' => $this->contactMessageId,
            ]);

            return;
        }

        $recipient = SystemVariable::query()
            ->where('club_id', $contactMessage->club_id)
            ->where('name', 'feedback_notification_email')
            ->value('value');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $recipient = $contactMessage->club->email;
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::warning('El parque no tiene un correo de contacto válido', [
                'contact_message_id' => $contactMessage->id,
                'club_id' => $contactMessage->club_id,
            ]);

            return;
        }

        $mailService->send(
            entityId: (int) $contactMessage->club_id,
            to: (string) $recipient,
            mailable: new WebsiteContactMessageMailable($contactMessage)
        );
    }
}
