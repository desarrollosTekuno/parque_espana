<?php

namespace App\Mail;

use App\Models\Feedback\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class FeedbackTicketNotificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $event,
        public string $recipientType = 'admin',
        public ?string $reviewUrl = null
    ) {
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['type', 'category', 'status', 'priority', 'reportedBy', 'attachments']);

        $isCancelled = $this->event === 'cancelled';
        $isClient = $this->recipientType === 'client';

        $subject = $isClient
            ? ($isCancelled
                ? 'Recibimos la cancelacion de tu ticket ' . $this->ticket->ticket_number
                : 'Recibimos tu queja/sugerencia ' . $this->ticket->ticket_number)
            : ($isCancelled
                ? 'Ticket cancelado: ' . $this->ticket->ticket_number
                : 'Nuevo ticket creado: ' . $this->ticket->ticket_number);

        $attachmentLinks = $this->ticket->attachments
            ->map(function ($attachment) {
                return [
                    'name' => $attachment->file_name,
                    'url' => Storage::disk($attachment->storage_disk ?: 'public')->url($attachment->file_path),
                    'path' => $attachment->file_path,
                    'disk' => $attachment->storage_disk ?: 'public',
                    'mime' => $attachment->file_type,
                ];
            })
            ->values()
            ->all();

        $mail = $this
            ->subject($subject)
            ->view('emails.feedback_ticket_notification', [
                'ticket' => $this->ticket,
                'event' => $this->event,
                'recipientType' => $this->recipientType,
                'reviewUrl' => $this->reviewUrl,
                'attachmentLinks' => $attachmentLinks,
                'subjectText' => $subject,
            ]);

        if ($this->recipientType === 'admin') {
            foreach ($attachmentLinks as $attachment) {
                if (!Storage::disk($attachment['disk'])->exists($attachment['path'])) {
                    continue;
                }

                $mail->attachFromStorageDisk(
                    $attachment['disk'],
                    $attachment['path'],
                    $attachment['name'],
                    ['mime' => $attachment['mime']]
                );
            }
        }

        return $mail;
    }
}
