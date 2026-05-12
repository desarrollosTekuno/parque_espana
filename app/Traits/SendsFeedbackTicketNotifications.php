<?php

namespace App\Traits;

use App\Mail\FeedbackTicketNotificationMailable;
use App\Models\AdminClub\SystemVariable;
use App\Models\Feedback\Ticket;
use App\Services\Email\MailService;

trait SendsFeedbackTicketNotifications {

    protected function sendTicketNotifications(MailService $mailService, Ticket $ticket, string $event): void {
        try {
            $clubId = (int) $ticket->club_id;
            $ticket->load(['type', 'category', 'status', 'priority', 'reportedBy', 'member', 'attachments']);

            $adminEmail = SystemVariable::where('club_id', $clubId)
                ->where('name', 'feedback_notification_email')
                ->value('value');

            $clientEmail = null;

            if (!$ticket->is_anonymous) {
                $clientEmail = $ticket->reportedBy?->email ?: $ticket->member?->email;
            }

            $reviewUrl = route('feedback-management.index', ['search' => $ticket->ticket_number]);

            if (is_string($adminEmail) && trim($adminEmail) !== '') {
                $mailService->send(
                    entityId: $clubId,
                    to: trim($adminEmail),
                    mailable: new FeedbackTicketNotificationMailable($ticket, $event, 'admin', $reviewUrl)
                );
            }

            if (is_string($clientEmail) && trim($clientEmail) !== '') {
                $mailService->send(
                    entityId: $clubId,
                    to: trim($clientEmail),
                    mailable: new FeedbackTicketNotificationMailable($ticket, $event, 'client')
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function sendTicketStatusNotification(MailService $mailService, Ticket $ticket): void {
        try {
            $clubId = (int) $ticket->club_id;
            $ticket->load(['type', 'category', 'status', 'priority', 'reportedBy', 'member', 'attachments']);

            if ($ticket->is_anonymous) {
                return;
            }

            $clientEmail = $ticket->reportedBy?->email ?: $ticket->member?->email;

            if (!is_string($clientEmail) || trim($clientEmail) === '') {
                return;
            }

            $mailService->send(
                entityId: $clubId,
                to: trim($clientEmail),
                mailable: new FeedbackTicketNotificationMailable($ticket, 'status_changed', 'client')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function sendTicketCommentNotification(MailService $mailService, Ticket $ticket): void {
        try {
            $clubId = (int) $ticket->club_id;
            $ticket->load(['type', 'category', 'status', 'priority', 'reportedBy', 'member', 'attachments']);

            if ($ticket->is_anonymous) {
                return;
            }

            $clientEmail = $ticket->reportedBy?->email ?: $ticket->member?->email;

            if (!is_string($clientEmail) || trim($clientEmail) === '') {
                return;
            }

            $mailService->send(
                entityId: $clubId,
                to: trim($clientEmail),
                mailable: new FeedbackTicketNotificationMailable($ticket, 'comment_added', 'client')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function sendTicketResolvedNotification(MailService $mailService, Ticket $ticket): void {
        try {
            $clubId = (int) $ticket->club_id;
            $ticket->load(['type', 'category', 'status', 'priority', 'reportedBy', 'member', 'attachments']);

            if ($ticket->is_anonymous) {
                return;
            }

            $clientEmail = $ticket->reportedBy?->email ?: $ticket->member?->email;

            if (!is_string($clientEmail) || trim($clientEmail) === '') {
                return;
            }

            $mailService->send(
                entityId: $clubId,
                to: trim($clientEmail),
                mailable: new FeedbackTicketNotificationMailable($ticket, 'resolved', 'client')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
