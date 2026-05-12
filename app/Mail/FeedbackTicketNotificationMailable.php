<?php

namespace App\Mail;

use App\Models\Feedback\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class FeedbackTicketNotificationMailable extends Mailable {
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
        $isStatusChanged = $this->event === 'status_changed';
        $isCommentAdded = $this->event === 'comment_added';
        $isClient = $this->recipientType === 'client';

        $subject = $isCommentAdded
            ? 'Nuevo comentario en tu ticket ' . $this->ticket->ticket_number
            : ($isStatusChanged
            ? 'Actualizacion de estatus de tu ticket ' . $this->ticket->ticket_number
            : ($isClient
            ? ($isCancelled
                ? 'Recibimos la cancelacion de tu ticket ' . $this->ticket->ticket_number
                : 'Recibimos tu queja/sugerencia ' . $this->ticket->ticket_number)
            : ($isCancelled
                ? 'Ticket cancelado: ' . $this->ticket->ticket_number
                : 'Nuevo ticket creado: ' . $this->ticket->ticket_number)));

        $attachmentLinks = $this->ticket->attachments
            ->map(function ($attachment) {
                return [
                    'name' => $attachment->file_name,
                    'url' => $attachment->public_url,
                    'path' => $attachment->storage_path,
                    'disk' => $attachment->storage_disk ?: 'public',
                    'mime' => $attachment->file_type,
                ];
            })
            ->values()
            ->all();

        $latestStatusComment = null;
        $latestTicketComment = null;

        if ($isStatusChanged) {
            $latestStatusComment = $this->ticket->comments()
                ->where('is_internal', false)
                ->latest('id')
                ->value('comment');
        }

        if ($isCommentAdded) {
            $latestTicketComment = $this->ticket->comments()
                ->where('is_internal', false)
                ->latest('id')
                ->value('comment');
        }

        $view = $isCommentAdded
            ? 'emails.feedback_ticket_comment_added'
            : ($isStatusChanged
            ? 'emails.feedback_ticket_status_changed'
            : ($isCancelled
                ? 'emails.feedback_ticket_cancelled'
                : 'emails.feedback_ticket_notification'));

        $mail = $this
            ->subject($subject)
            ->view($view, [
                'ticket' => $this->ticket,
                'event' => $this->event,
                'recipientType' => $this->recipientType,
                'reviewUrl' => $this->reviewUrl,
                'attachmentLinks' => $attachmentLinks,
                'latestStatusComment' => $latestStatusComment,
                'latestTicketComment' => $latestTicketComment,
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
