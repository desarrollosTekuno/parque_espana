<?php

namespace App\Services\Feedback;

use App\Mail\FeedbackTicketEventMailable;
use App\Models\Feedback\Ticket;
use App\Models\Notifications\EmailConfig;
use App\Models\User;
use App\Services\Email\MailService;

class FeedbackNotificationService
{
    public function __construct(private readonly MailService $mailService)
    {
    }

    public function notifyCreated(Ticket $ticket, ?int $excludeUserId = null): void
    {
        $this->notifyByEmail(
            ticket: $ticket,
            subject: 'Nuevo ticket de feedback: ' . ($ticket->ticket_number ?? ''),
            header: 'Nuevo ticket de feedback',
            message: "Se registro un nuevo ticket de feedback y ya se encuentra en estatus ENVIADO.",
            excludeUserId: $excludeUserId,
        );
    }

    public function notifyUpdated(Ticket $ticket, ?int $excludeUserId = null): void
    {
        $this->notifyByEmail(
            ticket: $ticket,
            subject: 'Actualizacion de ticket: ' . ($ticket->ticket_number ?? ''),
            header: 'Ticket de feedback actualizado',
            message: 'Se actualizo la informacion principal del ticket de feedback.',
            excludeUserId: $excludeUserId,
        );
    }

    public function notifyCancelled(Ticket $ticket, ?int $excludeUserId = null): void
    {
        $this->notifyByEmail(
            ticket: $ticket,
            subject: 'Ticket cancelado: ' . ($ticket->ticket_number ?? ''),
            header: 'Ticket de feedback cancelado',
            message: 'El ticket de feedback fue cancelado por el usuario solicitante.',
            excludeUserId: $excludeUserId,
        );
    }

    public function notifyStatusChanged(Ticket $ticket, string $statusName, ?int $excludeUserId = null): void
    {
        $this->notifyByEmail(
            ticket: $ticket,
            subject: 'Cambio de estatus: ' . ($ticket->ticket_number ?? ''),
            header: 'Cambio de estatus en feedback',
            message: 'El ticket cambio a estatus: ' . $statusName . '.',
            excludeUserId: $excludeUserId,
        );
    }

    public function notifyCommentAdded(Ticket $ticket, bool $isInternal = false, ?int $excludeUserId = null): void
    {
        $this->notifyByEmail(
            ticket: $ticket,
            subject: 'Nuevo comentario en ticket: ' . ($ticket->ticket_number ?? ''),
            header: 'Nuevo comentario en feedback',
            message: $isInternal
                ? 'Se agrego un comentario interno al ticket de feedback.'
                : 'Se agrego un nuevo comentario al ticket de feedback.',
            excludeUserId: $excludeUserId,
        );
    }

    private function notifyByEmail(
        Ticket $ticket,
        string $subject,
        string $header,
        string $message,
        ?int $excludeUserId = null
    ): void {
        $ticket->loadMissing(['status:id,name', 'priority:id,name', 'category:id,name', 'reportedBy:id,email', 'assignedTo:id,email', 'member:id,email']);

        $recipients = $this->resolveRecipients($ticket, $excludeUserId);

        if (empty($recipients)) {
            return;
        }

        $templateName = EmailConfig::query()
            ->where('entity_id', (int) $ticket->club_id)
            ->where('is_active', true)
            ->value('template_name') ?? 'emails.email_template';

        $this->mailService->send(
            entityId: (int) $ticket->club_id,
            to: $recipients,
            mailable: new FeedbackTicketEventMailable(
                subjectText: $subject,
                headerText: $header,
                messageText: $message,
                ticketData: [
                    'ticket_number' => $ticket->ticket_number,
                    'title' => $ticket->title,
                    'status' => $ticket->status?->name,
                    'priority' => $ticket->priority?->name,
                    'category' => $ticket->category?->name,
                ],
                templateName: $templateName,
            ),
        );

        // Hook reservado para notificaciones push.
        // $this->sendPushNotifications($ticket, $recipients, $subject, $message);
    }

    private function resolveRecipients(Ticket $ticket, ?int $excludeUserId = null): array
    {
        $emails = [];

        $clubUsers = User::query()
            ->whereHas('clubs', fn ($q) => $q->where('clubs.clubs.id', (int) $ticket->club_id))
            ->whereNotNull('email')
            ->get(['id', 'email']);

        foreach ($clubUsers as $user) {
            if ($excludeUserId !== null && (int) $user->id === (int) $excludeUserId) {
                continue;
            }

            if ($user->can('feedback-management.index') || $user->can('feedback-management.update')) {
                $emails[] = trim((string) $user->email);
            }
        }

        if (! $ticket->is_anonymous && $ticket->reportedBy?->email) {
            $emails[] = trim((string) $ticket->reportedBy->email);
        }

        if ($ticket->assignedTo?->email && ($excludeUserId === null || (int) $ticket->assigned_to_user_id !== $excludeUserId)) {
            $emails[] = trim((string) $ticket->assignedTo->email);
        }

        if ($ticket->member?->email) {
            $emails[] = trim((string) $ticket->member->email);
        }

        $emails = array_values(array_unique(array_filter($emails, fn ($email) => $email !== '')));

        return $emails;
    }
}
