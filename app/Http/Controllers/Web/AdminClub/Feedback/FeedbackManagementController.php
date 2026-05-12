<?php

namespace App\Http\Controllers\Web\AdminClub\Feedback;

use App\Models\Feedback\Priority;
use App\Models\Feedback\Comment;
use App\Models\Feedback\Status;
use App\Models\Feedback\StatusHistory;
use App\Models\Feedback\Ticket;
use App\Services\Email\MailService;
use App\Traits\SendsFeedbackTicketNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class FeedbackManagementController extends Controller {
    use SendsFeedbackTicketNotifications;

    public function __construct() {
        $this->middleware('permission:feedback-management.index')->only('index');
        $this->middleware('permission:feedback-management.update')->only('update');
    }

    public function index(Request $request) {
        $clubId = (int) ($request->club_id ?? session('club_id'));
        $driver = DB::getDriverName();

        $query = Ticket::with([
            'type:id,name,code',
            'category:id,name,code',
            'status:id,name,code,color',
            'priority:id,name,code',
            'member:id,first_name,last_name,second_last_name,email',
            'reportedBy:id,name,email',
            'assignedTo:id,name,email',

            'attachments' => fn($q) => $q->latest(),
            'attachments.uploadedBy:id,name,email',

            'comments' => fn($q) => $q->latest(),
            'comments.user:id,name,email',

            'statusHistory' => fn($q) => $q->latest(),
            'statusHistory.oldStatus:id,name,code,color',
            'statusHistory.newStatus:id,name,code,color',
            'statusHistory.changedBy:id,name,email',
        ])
        ->where('club_id', $clubId);

        if ($search = trim((string) $request->input('search'))) {
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $operator) {
                $q->where('ticket_number', $operator, "%{$search}%")
                    ->orWhere('title', $operator, "%{$search}%")
                    ->orWhere('description', $operator, "%{$search}%");
            });
        }

        if ($statusId = $request->input('status_id')) {
            $query->where('status_id', $statusId);
        }

        if ($priorityId = $request->input('priority_id')) {
            $query->where('priority_id', $priorityId);
        }

        $tickets = $query
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('AdminClubs/FeedbackManagement/Index', [
            'tickets' => $tickets,

            'statuses' => Status::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'color']),

            'priorities' => Priority::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code']),

            'filters' => [
                'search' => $request->input('search', ''),
                'status_id' => $request->input('status_id'),
                'priority_id' => $request->input('priority_id'),
            ],
        ]);
    }

    public function update(Request $request, Ticket $feedback, MailService $mailService) {
        $validated = $request->validate([
            'action' => 'required|string',
            'rejection_reason' => 'required_if:action,reject|string|max:1000',
            'comment' => 'required_if:action,comment|string|max:2000',
            'is_internal' => 'nullable|boolean',
            'status_id' => 'required_if:action,change_status|integer',
            'change_reason' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:2000',
            'resolution_notes' => 'nullable|string|max:4000',
            'is_internal' => 'nullable|boolean',
        ], [
            'action.required' => 'La acción es requerida.',
            'action.in' => 'La acción solicitada no es válida.',
            'rejection_reason.required_if' => 'Debes indicar el motivo del rechazo.',
            'comment.required_if' => 'Debes escribir un comentario.',
            'status_id.required_if' => 'Debes seleccionar un estatus.',
        ]);

        try {
            if ($validated['action'] === 'comment') {
                Comment::create([
                    'ticket_id' => $feedback->id,
                    'user_id' => Auth::id(),
                    'comment' => $validated['comment'],
                    'is_internal' => (bool) ($validated['is_internal'] ?? false),
                ]);

                if (!(bool) ($validated['is_internal'] ?? false)) {
                    $updatedTicket = Ticket::query()->findOrFail($feedback->id);
                    $this->sendTicketCommentNotification($mailService, $updatedTicket);
                }

                return back()->with('success', 'Comentario agregado correctamente.');
            }

            if ($validated['action'] === 'change_status') {
                $newStatus = Status::findOrFail((int) $validated['status_id']);

                if ((int) $feedback->status_id === (int) $newStatus->id) {
                    return back()->withErrors([
                        'messageError' => 'El ticket ya se encuentra en ese estatus.',
                    ]);
                }

                DB::transaction(function () use ($feedback, $newStatus, $validated) {
                    $oldStatusId = $feedback->status_id;
                    $newCode = strtoupper((string) $newStatus->code);
                    $commentText = trim((string) ($validated['comment'] ?? ''));
                    $changeReason = trim((string) ($validated['change_reason'] ?? ''));

                    if ($newCode === 'RESOLVED') {
                        $commentText = '';
                    }

                    $data = [
                        'status_id' => $newStatus->id,
                    ];

                    if (!empty($validated['resolution_notes'])) {
                        $data['resolution_notes'] = $validated['resolution_notes'];
                    }

                    if ($newCode === 'IN_PROGRESS') {
                        $data['closed_at'] = null;
                    }

                    if ($newCode === 'RESOLVED') {
                        $data['resolved_at'] = now();
                    }

                    if ($newCode === 'CANCELLED') {
                        $data['closed_at'] = now();
                    }

                    $feedback->update($data);

                    StatusHistory::create([
                        'ticket_id' => $feedback->id,
                        'old_status_id' => $oldStatusId,
                        'new_status_id' => $newStatus->id,
                        'change_reason' => $changeReason !== '' ? $changeReason : ($commentText !== '' ? $commentText : null),
                        'changed_by_user_id' => Auth::id(),
                    ]);

                    if ($commentText !== '') {
                        Comment::create([
                            'ticket_id' => $feedback->id,
                            'user_id' => Auth::id(),
                            'comment' => $commentText,
                            'is_internal' => false,
                        ]);
                    }
                });

                $updatedTicket = Ticket::query()->findOrFail($feedback->id);
                $this->sendTicketStatusNotification($mailService, $updatedTicket);

                return back()->with('success', 'Estatus actualizado correctamente.');
            }

            $rejectedStatus = Status::where('code', 'REJECTED')->firstOrFail();

            if ((int) $feedback->status_id === (int) $rejectedStatus->id) {
                return back()->withErrors([
                    'messageError' => 'El ticket ya se encuentra rechazado.',
                ]);
            }

            DB::transaction(function () use ($feedback, $rejectedStatus, $validated) {
                $oldStatusId = $feedback->status_id;

                $feedback->update([
                    'status_id' => $rejectedStatus->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $validated['rejection_reason'],
                    'closed_at' => now(),
                ]);

                StatusHistory::create([
                    'ticket_id' => $feedback->id,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $rejectedStatus->id,
                    'change_reason' => $validated['rejection_reason'],
                    'changed_by_user_id' => Auth::id(),
                ]);

                Comment::create([
                    'ticket_id' => $feedback->id,
                    'user_id' => Auth::id(),
                    'comment' => 'Ticket rechazado: ' . $validated['rejection_reason'],
                    'is_internal' => true,
                ]);
            });

            $updatedTicket = Ticket::query()->findOrFail($feedback->id);
            $this->sendTicketStatusNotification($mailService, $updatedTicket);

            return back()->with('success', 'Ticket rechazado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al rechazar el ticket.',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
