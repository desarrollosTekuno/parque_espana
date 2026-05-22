<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackTicketRequest;
use App\Models\Administrator\Club;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use App\Models\Members\Member;
use App\Services\Email\MailService;
use App\Traits\HandlesFeedbackTickets;
use App\Traits\SendsFeedbackTicketNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackTicketMobileController extends Controller
{
    use HandlesFeedbackTickets;
    use SendsFeedbackTicketNotifications;

    /**
     * GET /api/v1/clubs/{club}/feedback/tickets
     *
     * Historial de quejas y sugerencias del socio autenticado.
     *
     * Query params opcionales:
     *   ?type=COMPLAINT|SUGGESTION   filtra por tipo (code)
     *   ?status=SUBMITTED|IN_REVIEW|RESOLVED|CANCELLED   filtra por estatus (code)
     */
    public function index(Request $request, Club $club): JsonResponse
    {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $query = Ticket::with(['type', 'category', 'status', 'priority'])
                ->where('club_id', $club->id)
                ->where(function ($q) use ($request, $member) {
                    $q->where('reported_by_user_id', $request->user()->id);
                    if ($member) {
                        $q->orWhere('member_id', $member->id);
                    }
                });

            // Filtro por tipo (code del ticket_type)
            if ($request->filled('type')) {
                $query->whereHas('type', fn($q) => $q->where('code', strtoupper($request->type)));
            }

            // Filtro por estatus (code del status)
            if ($request->filled('status')) {
                $query->whereHas('status', fn($q) => $q->where('code', strtoupper($request->status)));
            }

            $tickets = $query
                ->orderByDesc('submitted_at')
                ->get()
                ->map(fn($ticket) => $this->formatTicketSummary($ticket))
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Tickets obtenidos correctamente',
                'tickets' => $tickets,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success'      => false,
                'message'      => 'Error al obtener tickets',
                'tickets'      => [],
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/clubs/{club}/feedback/tickets/{ticket}
     *
     * Detalle completo de un ticket: datos, adjuntos y comentarios públicos.
     */
    public function show(Request $request, Club $club, Ticket $ticket): JsonResponse
    {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            // Verificar que el ticket pertenece al usuario autenticado
            $belongs = $ticket->club_id === $club->id && (
                $ticket->reported_by_user_id === $request->user()->id ||
                ($member && $ticket->member_id === $member->id)
            );

            if (!$belongs) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket no encontrado',
                ], 404);
            }

            $ticket->load([
                'type',
                'category',
                'status',
                'priority',
                'attachments',
                // Solo comentarios públicos — los internos son del equipo admin
                'comments' => fn($q) => $q->where('is_internal', false)->orderBy('created_at'),
                'comments.user:id,name',
            ]);

            return response()->json([
                'success' => true,
                'ticket'  => $this->formatTicketDetail($ticket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success'       => false,
                'message'       => 'Error al obtener el ticket',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/clubs/{club}/feedback/tickets
     */
    public function store(StoreFeedbackTicketRequest $request, Club $club, MailService $mailService): JsonResponse
    {
        try {
            $status       = Status::where('code', 'SUBMITTED')->first();
            $member       = Member::where('user_id', $request->user()->id)->first();
            $ticketNumber = $this->createTicketNumber((int) $club->id);

            if (!$status) {
                return response()->json(['success' => false, 'message' => 'No se encontro estatus SUBMITTED'], 422);
            }

            if (!$ticketNumber) {
                return response()->json(['success' => false, 'message' => 'No se pudo generar folio unico para el ticket'], 422);
            }

            $ticket = Ticket::create([
                'ticket_number'        => $ticketNumber,
                'ticket_date'          => now(),
                'club_id'              => $club->id,
                'ticket_type_id'       => $request->ticket_type_id,
                'category_id'          => $request->category_id,
                'status_id'            => $status->id,
                'priority_id'          => $request->priority_id,
                'member_id'            => $member?->id,
                'reported_by_user_id'  => $request->boolean('is_anonymous', false) ? null : $request->user()->id,
                'assigned_to_user_id'  => null,
                'title'                => $request->title,
                'description'          => $request->description,
                'resolution_notes'     => null,
                'rejected_at'          => null,
                'rejection_reason'     => null,
                'resolved_at'          => null,
                'closed_at'            => null,
                'due_at'               => null,
                'is_anonymous'         => $request->boolean('is_anonymous', false),
                'submitted_at'         => now(),
            ]);

            $attachments = $this->getAttachmentFiles($request);
            if (!empty($attachments)) {
                $this->storeFileAttachments($ticket, $attachments);
            }

            $this->sendTicketNotifications($mailService, $ticket);

            return response()->json([
                'success' => true,
                'message' => 'Ticket creado correctamente',
                'ticket'  => $ticket,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success'       => false,
                'message'       => 'Error al crear ticket',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/clubs/{club}/feedback/tickets/{ticket}/cancel
     */
    public function cancel(Request $request, Club $club, Ticket $ticket, MailService $mailService): JsonResponse
    {
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
                return response()->json(['success' => false, 'message' => 'Ticket no encontrado para este usuario'], 404);
            }

            $submittedStatus = Status::where('code', 'SUBMITTED')->first();
            $cancelledStatus = Status::where('code', 'CANCELLED')->first();

            if (!$submittedStatus || !$cancelledStatus) {
                return response()->json(['success' => false, 'message' => 'No se encontraron estatus requeridos'], 422);
            }

            if ((int) $ticketQuery->status_id !== (int) $submittedStatus->id) {
                return response()->json(['success' => false, 'message' => 'Solo se puede cancelar cuando el ticket esta ENVIADO'], 422);
            }

            $ticketQuery->update([
                'status_id' => $cancelledStatus->id,
                'closed_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Ticket cancelado correctamente']);

        } catch (\Exception $e) {
            return response()->json([
                'success'       => false,
                'message'       => 'Error al cancelar ticket',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers de formato
    // ─────────────────────────────────────────────────────────────────────────

    private function formatTicketSummary(Ticket $ticket): array
    {
        return [
            'id'             => $ticket->id,
            'ticket_number'  => $ticket->ticket_number ?? '',
            'title'          => $ticket->title ?? '',
            'submitted_at'   => $ticket->submitted_at?->toDateTimeString() ?? '',
            'resolved_at'    => $ticket->resolved_at?->toDateTimeString() ?? '',
            'closed_at'      => $ticket->closed_at?->toDateTimeString() ?? '',
            'is_anonymous'   => $ticket->is_anonymous,
            'ticket_type'    => [
                'id'   => $ticket->type->id   ?? 0,
                'name' => $ticket->type->name ?? '',
                'code' => $ticket->type->code ?? '',
            ],
            'category'       => [
                'id'   => $ticket->category->id   ?? 0,
                'name' => $ticket->category->name ?? '',
                'code' => $ticket->category->code ?? '',
            ],
            'status'         => [
                'id'    => $ticket->status->id    ?? 0,
                'name'  => $ticket->status->name  ?? '',
                'code'  => $ticket->status->code  ?? '',
                'color' => $ticket->status->color ?? '',
            ],
            'priority'       => [
                'id'   => $ticket->priority->id   ?? 0,
                'name' => $ticket->priority->name ?? '',
                'code' => $ticket->priority->code ?? '',
            ],
        ];
    }

    private function formatTicketDetail(Ticket $ticket): array
    {
        $summary = $this->formatTicketSummary($ticket);

        return array_merge($summary, [
            'description'      => $ticket->description ?? '',
            'resolution_notes' => $ticket->resolution_notes ?? '',
            'rejection_reason' => $ticket->rejection_reason ?? '',
            'due_at'           => $ticket->due_at ?? '',
            'attachments'      => $ticket->attachments->map(fn($a) => [
                'id'         => $a->id,
                'url'        => $a->public_url,
                'file_name'  => basename($a->file_path ?? ''),
            ])->values(),
            'comments'         => $ticket->comments->map(fn($c) => [
                'id'         => $c->id,
                'body'       => $c->comment,
                'author'     => $c->user?->name ?? 'Soporte',
                'created_at' => $c->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
