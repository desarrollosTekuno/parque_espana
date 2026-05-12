<?php

namespace App\Http\Controllers\Web\AdminClub\Feedback;

use Illuminate\Routing\Controller;
use App\Http\Requests\StoreFeedbackTicketRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Feedback\Ticket;
use App\Models\Feedback\Category;
use App\Models\Feedback\TicketType;
use App\Models\Feedback\Status;
use App\Models\Feedback\Priority;
use App\Services\Email\MailService;
use App\Traits\HandlesFeedbackTickets;
use App\Traits\SendsFeedbackTicketNotifications;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller {
    use HandlesFeedbackTickets;
    use SendsFeedbackTicketNotifications;

    public function __construct() {
        $this->middleware('permission:feedback.index')->only('index');
        $this->middleware('permission:feedback.store')->only('store');
        $this->middleware('permission:feedback.update')->only('update');
        $this->middleware('permission:feedback.update')->only('cancel');
        $this->middleware('permission:feedback.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();

            $query = Ticket::with([
                'type',
                'category',
                'status',
                'priority',
                'member',
                'reportedBy',
                'assignedTo',
                'attachments' => fn ($q) => $q->latest(),
                'attachments.uploadedBy:id,name',
                'comments' => fn ($q) => $q->where('is_internal', false)->latest(),
                'comments.user:id,name',
                'statusHistory' => fn ($q) => $q->latest(),
                'statusHistory.oldStatus:id,name,code,color',
                'statusHistory.newStatus:id,name,code,color',
                'statusHistory.changedBy:id,name',
            ])->where('club_id', $clubId);

            $user = Auth::user();
            $canViewAllTickets = $user && $user->hasAnyRole(['superadmin', 'admin_club']);

            if (!$canViewAllTickets) {
                $query->where('reported_by_user_id', Auth::id());
            }

            if ($search = trim($request->input('search'))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('ticket_number', $operator, "%{$search}%")
                        ->orWhere('title', $operator, "%{$search}%")
                        ->orWhere('description', $operator, "%{$search}%");
                });
            }

            $tickets = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->through(function ($item) {
                    return [
                        'id' => $item->id,
                        'ticket_number' => $item->ticket_number,
                        'title' => $item->title,
                        'description' => $item->description,
                        'resolution_notes' => $item->resolution_notes,
                        'is_anonymous' => $item->is_anonymous,
                        'ticket_date' => $item->ticket_date,
                        'submitted_at' => $item->submitted_at,
                        'resolved_at' => $item->resolved_at,
                        'closed_at' => $item->closed_at,

                        'ticket_type_id' => $item->ticket_type_id,
                        'category_id' => $item->category_id,
                        'status_id' => $item->status_id,
                        'priority_id' => $item->priority_id,
                        'member_id' => $item->member_id,
                        'assigned_to_user_id' => $item->assigned_to_user_id,

                        'type' => $item->type,
                        'category' => $item->category,
                        'status' => $item->status,
                        'priority' => $item->priority,
                        'member' => $item->member,
                        'reported_by' => $item->reportedBy,
                        'assigned_to' => $item->assignedTo,
                        'attachments' => $item->attachments->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                            'storage_path' => $attachment->storage_path,
                            'file_url' => $attachment->public_url,
                            'file_type' => $attachment->file_type,
                            'file_size' => $attachment->file_size,
                            'uploaded_by' => $attachment->uploadedBy,
                        ])->values(),
                        'comments' => $item->comments->map(fn ($comment) => [
                            'id' => $comment->id,
                            'comment' => $comment->comment,
                            'is_internal' => $comment->is_internal,
                            'created_at' => $comment->created_at,
                            'user' => $comment->user,
                        ])->values(),
                        'status_history' => $item->statusHistory->map(fn ($history) => [
                            'id' => $history->id,
                            'old_status' => $history->oldStatus,
                            'new_status' => $history->newStatus,
                            'changed_by' => $history->changedBy,
                            'change_reason' => $history->change_reason,
                            'created_at' => $history->created_at,
                        ])->values(),
                    ];
                })
                ->withQueryString();

            return Inertia::render('AdminClubs/Feedback/Index', [
                'tickets' => $tickets,
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
                'ticketTypes' => TicketType::where('is_active', true)->orderBy('name')->get(),
                'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(),
                'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            ]);

        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/Feedback/Index', [
                'tickets' => [
                    'data' => [],
                    'total' => 0,
                ],
                'categories' => [],
                'ticketTypes' => [],
                'statuses' => [],
                'priorities' => [],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(StoreFeedbackTicketRequest $request, MailService $mailService) {
        try {
            $status = Status::where('code', 'SUBMITTED')->firstOrFail();
            $clubId = (int) session('club_id');
            $ticketNumber = $this->createTicketNumber($clubId);

            if (!$ticketNumber) {
                return back()->withErrors([
                    'messageError' => 'No se pudo generar un folio unico para el ticket. Intenta nuevamente.',
                ]);
            }

            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'ticket_date' => now(),
                'club_id' => $clubId,
                'ticket_type_id' => $request->ticket_type_id,
                'category_id' => $request->category_id,
                'status_id' => $status->id,
                'priority_id' => $request->priority_id,
                'member_id' => null,
                'reported_by_user_id' => $request->is_anonymous ? null : Auth::id(),
                'assigned_to_user_id' => null,
                'title' => $request->title,
                'description' => $request->description,
                'resolution_notes' => null,
                'ticket_date' => now(),
                'due_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'resolved_at' => null,
                'closed_at' => null,
                'is_anonymous' => $request->is_anonymous,
            ]);

            $attachments = $this->getAttachmentFiles($request);

            if (!empty($attachments)) {
                $this->storeTicketAttachments($ticket, $attachments);
            }

            $this->sendTicketNotifications($mailService, $ticket, 'created');

            return back()->with('success', 'Queja o sugerencia creada correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear la queja o sugerencia',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, Ticket $feedback) {
        $request->validate([
            'ticket_type_id' => 'required',
            'category_id' => 'required',
            'status_id' => 'required',
            'priority_id' => 'required',
            'title' => 'required|string|max:85',
            'description' => 'required|string|max:350',
            'is_anonymous' => 'required|boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ], [
            'ticket_type_id.required' => 'Debes seleccionar un tipo.',
            'category_id.required' => 'Debes seleccionar una categoría.',
            'status_id.required' => 'Debes seleccionar un estatus.',
            'priority_id.required' => 'Debes seleccionar una prioridad.',
            'title.required' => 'Debes ingresar un título.',
            'title.max' => 'El título no puede exceder 85 caracteres.',
            'description.required' => 'Debes ingresar una descripción.',
            'attachments.array' => 'Los adjuntos deben enviarse como una lista de archivos.',
            'attachments.max' => 'Solo puedes subir hasta 5 archivos por ticket.',
            'attachments.*.file' => 'Cada adjunto debe ser un archivo válido.',
            'attachments.*.mimes' => 'Formato no permitido. Usa: jpg, jpeg, png, pdf, doc, docx, xls o xlsx.',
            'attachments.*.max' => 'Cada archivo puede pesar máximo 10 MB.',
        ]);

        try {
            $dataToUpdate = [
                'ticket_type_id' => $request->ticket_type_id,
                'category_id' => $request->category_id,
                'status_id' => $request->status_id,
                'priority_id' => $request->priority_id,
                'member_id' => $feedback->member_id,
                'reported_by_user_id' => $feedback->reported_by_user_id,
                'assigned_to_user_id' => $feedback->assigned_to_user_id,
                'title' => $request->title,
                'description' => $request->description,
                'resolution_notes' => $feedback->resolution_notes,
                'is_anonymous' => $request->is_anonymous,
            ];

            if ($request->has('resolution_notes')) {
                $dataToUpdate['resolution_notes'] = $request->resolution_notes;
            }

            $feedback->update($dataToUpdate);

            $attachments = $this->getAttachmentFiles($request);

            if (!empty($attachments)) {
                $this->storeTicketAttachments($feedback, $attachments);
            }

            return back()->with('success', 'Queja o sugerencia actualizada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la queja o sugerencia',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function cancelTicket(Ticket $feedback, MailService $mailService) {
        try {
            $submittedStatus = Status::where('code', 'SUBMITTED')->firstOrFail();
            $cancelledStatus = Status::where('code', 'CANCELLED')->firstOrFail();

            if ((int) $feedback->status_id !== (int) $submittedStatus->id) {
                return back()->withErrors([
                    'messageError' => 'Solo se puede cancelar cuando el ticket esta ENVIADO.',
                ]);
            }

            $feedback->update([
                'status_id' => $cancelledStatus->id,
                'closed_at' => now(),
            ]);

            $this->sendTicketNotifications($mailService, $feedback->fresh(), 'cancelled');

            return back()->with('success', 'Ticket cancelado correctamente');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al cancelar el ticket',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(Ticket $feedback) {
        try {
            $feedback->delete();

            return back()->with('success', 'Queja o sugerencia eliminada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar la queja o sugerencia',
                'exception' => $e->getMessage(),
            ]);
        }
    }

}
