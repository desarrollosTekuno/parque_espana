<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackTicketRequest;
use App\Mail\FeedbackTicketNotificationMailable;
use App\Models\Administrator\Club;
use App\Models\AdminClub\SystemVariable;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use App\Models\Members\Member;
use App\Services\Email\MailService;
use App\Traits\HandlesFeedbackTickets;
use Illuminate\Http\Request;

class FeedbackTicketMobileController extends Controller {
    use HandlesFeedbackTickets;

    public function index(Request $request, Club $club) {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $tickets = Ticket::with(['type', 'category', 'status', 'priority'])
                ->where('club_id', $club->id)
                ->where(function ($q) use ($request, $member) {
                    $q->where('reported_by_user_id', $request->user()->id);

                    if ($member) {
                        $q->orWhere('member_id', $member->id);
                    }
                })
                ->orderBy('id', 'desc')
                ->get();

            $tickets = $tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number ?? '',
                    'ticket_date' => $ticket->ticket_date ?? '',
                    'title' => $ticket->title ?? '',
                    'description' => $ticket->description ?? '',
                    'resolution_notes' => $ticket->resolution_notes ?? '',
                    'rejected_at' => $ticket->rejected_at ?? '',
                    'rejection_reason' => $ticket->rejection_reason ?? '',
                    'is_anonymous' => $ticket->is_anonymous,
                    'submitted_at' => $ticket->submitted_at ?? '',
                    'resolved_at' => $ticket->resolved_at ?? '',
                    'closed_at' => $ticket->closed_at ?? '',
                    'due_at' => $ticket->due_at ?? '',
                    'ticket_type' => [
                        'id' => $ticket->type->id ?? 0,
                        'name' => $ticket->type->name ?? '',
                        'code' => $ticket->type->code ?? '',
                    ],
                    'category' => [
                        'id' => $ticket->category->id ?? 0,
                        'name' => $ticket->category->name ?? '',
                        'code' => $ticket->category->code ?? '',
                    ],
                    'status' => [
                        'id' => $ticket->status->id ?? 0,
                        'name' => $ticket->status->name ?? '',
                        'code' => $ticket->status->code ?? '',
                        'color' => $ticket->status->color ?? '',
                    ],
                    'priority' => [
                        'id' => $ticket->priority->id ?? 0,
                        'name' => $ticket->priority->name ?? '',
                        'code' => $ticket->priority->code ?? '',
                    ],
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Tickets obtenidos correctamente',
                'tickets' => $tickets,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tickets',
                'tickets' => [],
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreFeedbackTicketRequest $request, Club $club, MailService $mailService) {
        try {
            $status = Status::where('code', 'SUBMITTED')->first();
            $member = Member::where('user_id', $request->user()->id)->first();
            $ticketNumber = $this->createTicketNumber((int) $club->id);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro estatus SUBMITTED',
                ], 422);
            } else if (!$ticketNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo generar folio unico para el ticket',
                ], 422);
            } else {
                $ticket = Ticket::create([
                    'ticket_number' => $ticketNumber,
                    'ticket_date' => now(),
                    'club_id' => $club->id,
                    'ticket_type_id' => $request->ticket_type_id,
                    'category_id' => $request->category_id,
                    'status_id' => $status->id,
                    'priority_id' => $request->priority_id,
                    'member_id' => $member?->id,
                    'reported_by_user_id' => $request->boolean('is_anonymous', false) ? null : $request->user()->id,
                    'assigned_to_user_id' => null,
                    'title' => $request->title,
                    'description' => $request->description,
                    'resolution_notes' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'resolved_at' => null,
                    'closed_at' => null,
                    'due_at' => null,
                    'is_anonymous' => $request->boolean('is_anonymous', false),
                    'submitted_at' => now(),
                ]);

                $attachments = $this->getAttachmentFiles($request);

                if (!empty($attachments)) {
                    $this->storeTicketAttachments($ticket, $attachments);
                }

                $this->sendTicketNotifications($mailService, $ticket, 'created');

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket creado correctamente',
                    'ticket' => $ticket,
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear ticket',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, Club $club, Ticket $ticket, MailService $mailService) {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $ticketQuery = Ticket::where('id', $ticket->id)
                ->where('club_id', $club->id)
                ->where(function ($q) use ($request, $member) {
                    $q->where('reported_by_user_id', $request->user()->id);

                    if ($member) {
                        $q->orWhere('member_id', $member->id);
                    }
                })
                ->first();

            if (!$ticketQuery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket no encontrado para este usuario',
                ], 404);
            } else {
                $submittedStatus = Status::where('code', 'SUBMITTED')->first();
                $cancelledStatus = Status::where('code', 'CANCELLED')->first();

                if (!$submittedStatus || !$cancelledStatus) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontraron estatus requeridos',
                    ], 422);
                } else if ((int) $ticketQuery->status_id !== (int) $submittedStatus->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo se puede cancelar cuando el ticket esta ENVIADO',
                    ], 422);
                } else {
                    $ticketQuery->update([
                        'status_id' => $cancelledStatus->id,
                        'closed_at' => now(),
                    ]);

                    $this->sendTicketNotifications($mailService, $ticketQuery->fresh(), 'cancelled');

                    return response()->json([
                        'success' => true,
                        'message' => 'Ticket cancelado correctamente',
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar ticket',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    private function sendTicketNotifications(MailService $mailService, Ticket $ticket, string $event): void {
        try {
            $clubId = (int) $ticket->club_id;
            $ticket->loadMissing(['type', 'category', 'status', 'priority', 'reportedBy', 'member', 'attachments']);

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
}
