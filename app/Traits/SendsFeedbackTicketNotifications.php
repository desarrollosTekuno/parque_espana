<?php

namespace App\Traits;

use App\Mail\FeedbackTicketCancelledMailable;
use App\Mail\FeedbackTicketCommentAddedMailable;
use App\Mail\FeedbackTicketCreatedMailable;
use App\Mail\FeedbackTicketRejectedMailable;
use App\Mail\FeedbackTicketResolvedMailable;
use App\Mail\FeedbackTicketStatusChangedMailable;
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
                $adminMailable = $event === 'cancelled'
                    ? new FeedbackTicketCancelledMailable($ticket, 'admin', $reviewUrl)
                    : new FeedbackTicketCreatedMailable($ticket, 'admin', $reviewUrl);

                $mailService->send(
                    entityId: $clubId,
                    to: trim($adminEmail),
                    mailable: $adminMailable
                );
            }

            if (is_string($clientEmail) && trim($clientEmail) !== '') {
                $clientMailable = $event === 'cancelled'
                    ? new FeedbackTicketCancelledMailable($ticket, 'client')
                    : new FeedbackTicketCreatedMailable($ticket, 'client');

                $mailService->send(
                    entityId: $clubId,
                    to: trim($clientEmail),
                    mailable: $clientMailable
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
                mailable: new FeedbackTicketStatusChangedMailable($ticket)
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
                mailable: new FeedbackTicketCommentAddedMailable($ticket)
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
                mailable: new FeedbackTicketResolvedMailable($ticket)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function sendTicketRejectedNotification(MailService $mailService, Ticket $ticket): void {
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
                mailable: new FeedbackTicketRejectedMailable($ticket)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
