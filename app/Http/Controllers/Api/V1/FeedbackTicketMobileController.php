<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackTicketCommentRequest;
use App\Http\Requests\StoreFeedbackTicketRequest;
use App\Models\Administrator\Club;
use App\Models\Feedback\Category;
use App\Models\Feedback\Comment;
use App\Models\Feedback\Priority;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use App\Models\Feedback\TicketType;
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

            if ($request->filled('type')) {
                $query->whereHas('type', fn ($q) => $q->where('code', strtoupper($request->type)));
            }

            if ($request->filled('status')) {
                $query->whereHas('status', fn ($q) => $q->where('code', strtoupper($request->status)));
            }

            $tickets = $query
                ->orderByDesc('submitted_at')
                ->get()
                ->map(fn ($ticket) => $this->formatTicketSummary($ticket))
                ->values();

            return $this->ok($tickets);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener tickets.');
        }
    }

    /**
     * GET /api/v1/clubs/{club}/feedback/options
     *
     * Catálogos para armar el formulario de "nueva queja/sugerencia": categorías, tipos de ticket y prioridades.
     */
    public function options(Club $club): JsonResponse
    {
        try {
            return $this->ok([
                'categories'   => Category::where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($category) => [
                        'id'          => $category->id,
                        'name'        => $category->name,
                        'code'        => $category->code,
                        'description' => $category->description,
                    ]),
                'ticket_types' => TicketType::where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($type) => [
                        'id'   => $type->id,
                        'name' => $type->name,
                        'code' => $type->code,
                    ]),
                'priorities'   => Priority::where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($priority) => [
                        'id'   => $priority->id,
                        'name' => $priority->name,
                        'code' => $priority->code,
                    ]),
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener los catálogos de feedback.');
        }
    }

    /**
     * GET /api/v1/clubs/{club}/feedback/tickets/{ticket}
     */
    public function show(Request $request, Club $club, Ticket $ticket): JsonResponse
    {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $belongs = $ticket->club_id === $club->id && (
                $ticket->reported_by_user_id === $request->user()->id ||
                ($member && $ticket->member_id === $member->id)
            );

            if (!$belongs) {
                return $this->notFound('Ticket no encontrado.');
            }

            $ticket->load([
                'type', 'category', 'status', 'priority', 'attachments',
                'comments'      => fn ($q) => $q->where('is_internal', false)->orderBy('created_at'),
                'comments.user:id,name',
            ]);

            return $this->ok($this->formatTicketDetail($ticket));
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener el ticket.');
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
                return $this->unprocessable('No se encontró el estatus SUBMITTED.');
            }

            if (!$ticketNumber) {
                return $this->unprocessable('No se pudo generar un folio único para el ticket.');
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

            return $this->created('Ticket creado correctamente.', $ticket);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al crear el ticket.');
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
                return $this->notFound('Ticket no encontrado para este usuario.');
            }

            $submittedStatus = Status::where('code', 'SUBMITTED')->first();
            $cancelledStatus = Status::where('code', 'CANCELLED')->first();

            if (!$submittedStatus || !$cancelledStatus) {
                return $this->unprocessable('No se encontraron los estatus requeridos.');
            }

            if ((int) $ticketQuery->status_id !== (int) $submittedStatus->id) {
                return $this->unprocessable('Solo se puede cancelar cuando el ticket está ENVIADO.');
            }

            $ticketQuery->update([
                'status_id' => $cancelledStatus->id,
                'closed_at' => now(),
            ]);

            return $this->success('Ticket cancelado correctamente.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al cancelar el ticket.');
        }
    }

    /**
     * POST /api/v1/clubs/{club}/feedback/tickets/{ticket}/comments
     */
    public function comment(StoreFeedbackTicketCommentRequest $request, Club $club, Ticket $ticket): JsonResponse
    {  
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $belongs = $ticket->club_id === $club->id && (
                $ticket->reported_by_user_id === $request->user()->id ||
                ($member && $ticket->member_id === $member->id)
            );

            if (!$belongs) {
                return $this->notFound('Ticket no encontrado.');
            }

            $comment = Comment::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $request->user()->id,
                'comment'     => $request->comment,
                'is_internal' => false,
            ]);

            $comment->load('user:id,name');

            return $this->created('Comentario agregado correctamente.', [
                'id'         => $comment->id,
                'body'       => $comment->comment,
                'author'     => $comment->user?->name ?? 'Usuario',
                'created_at' => $comment->created_at?->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al agregar el comentario.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers de formato
    // ─────────────────────────────────────────────────────────────────────────

    private function formatTicketSummary(Ticket $ticket): array
    {
        return [
            'id'            => $ticket->id,
            'ticket_number' => $ticket->ticket_number ?? '',
            'title'         => $ticket->title ?? '',
            'submitted_at'  => $ticket->submitted_at?->toDateTimeString() ?? '',
            'resolved_at'   => $ticket->resolved_at?->toDateTimeString() ?? '',
            'closed_at'     => $ticket->closed_at?->toDateTimeString() ?? '',
            'is_anonymous'  => $ticket->is_anonymous,
            'ticket_type'   => [
                'id'   => $ticket->type->id   ?? 0,
                'name' => $ticket->type->name ?? '',
                'code' => $ticket->type->code ?? '',
            ],
            'category'      => [
                'id'   => $ticket->category->id   ?? 0,
                'name' => $ticket->category->name ?? '',
                'code' => $ticket->category->code ?? '',
            ],
            'status'        => [
                'id'    => $ticket->status->id    ?? 0,
                'name'  => $ticket->status->name  ?? '',
                'code'  => $ticket->status->code  ?? '',
                'color' => $ticket->status->color ?? '',
            ],
            'priority'      => [
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
            'attachments'      => $ticket->attachments->map(fn ($a) => [
                'id'        => $a->id,
                'url'       => $a->public_url,
                'file_name' => basename($a->file_path ?? ''),
            ])->values(),
            'comments'         => $ticket->comments->map(fn ($c) => [
                'id'         => $c->id,
                'body'       => $c->comment,
                'author'     => $c->user?->name ?? 'Soporte',
                'created_at' => $c->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
