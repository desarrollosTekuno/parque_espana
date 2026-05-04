<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Feedback\Attachment;
use App\Models\Feedback\Category;
use App\Models\Feedback\Priority;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use App\Models\Feedback\TicketType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeedbackTicketController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-tickets.index')->only('index');
        $this->middleware('permission:feedback-tickets.store')->only('store');
        $this->middleware('permission:feedback-tickets.update')->only('update');
        $this->middleware('permission:feedback-tickets.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();

            $query = Ticket::with(['type', 'category', 'status', 'priority', 'attachments'])
                ->where('club_id', $clubId);

            if ($request->filled('ticket_type_id')) {
                $query->where('ticket_type_id', $request->ticket_type_id);
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('status_id')) {
                $query->where('status_id', $request->status_id);
            }

            if ($request->filled('priority_id')) {
                $query->where('priority_id', $request->priority_id);
            }

            if ($search = trim($request->input('search'))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('ticket_number', $operator, "%{$search}%")
                        ->orWhere('title', $operator, "%{$search}%")
                        ->orWhere('description', $operator, "%{$search}%")
                        ->orWhere('rejection_reason', $operator, "%{$search}%");
                });
            }

            $tickets = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->withQueryString();

            return Inertia::render('AdminClubs/FeedbackTickets/Index', [
                'tickets' => $tickets,
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
                'ticketTypes' => TicketType::where('is_active', true)->orderBy('name')->get(),
                'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(),
                'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/FeedbackTickets/Index', [
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

    public function store(Request $request) {
        $request->validate([
            'ticket_type_id' => 'required|exists:feedback.ticket_types,id',
            'category_id' => 'required|exists:feedback.categories,id',
            'priority_id' => 'required|exists:feedback.priorities,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'is_anonymous' => 'required|boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        try {
            $status = Status::where('code', 'SUBMITTED')->firstOrFail();

            $ticket = Ticket::create([
                'ticket_number' => $this->generateTicketNumber(),
                'club_id' => session('club_id'),
                'ticket_type_id' => $request->ticket_type_id,
                'category_id' => $request->category_id,
                'status_id' => $status->id,
                'priority_id' => $request->priority_id,
                'member_id' => $request->member_id,
                'reported_by_user_id' => $request->is_anonymous ? null : Auth::id(),
                'assigned_to_user_id' => $request->assigned_to_user_id,
                'title' => $request->title,
                'description' => $request->description,
                'resolution_notes' => $request->resolution_notes,
                'is_anonymous' => $request->is_anonymous,
                'ticket_date' => now(),
                'submitted_at' => now(),
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('feedback/tickets', 'public');

                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'storage_disk' => 'public',
                        'uploaded_by_user_id' => Auth::id(),
                    ]);
                }
            }

            return back()->with('success', 'Ticket de feedback creado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear el ticket de feedback',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id) {
        $request->validate([
            'ticket_type_id' => 'required|exists:feedback.ticket_types,id',
            'category_id' => 'required|exists:feedback.categories,id',
            'status_id' => 'required|exists:feedback.statuses,id',
            'priority_id' => 'required|exists:feedback.priorities,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'is_anonymous' => 'required|boolean',
            'rejection_reason' => 'sometimes|nullable|string|max:500',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        try {
            $ticket = Ticket::findOrFail($id);
            $newStatus = Status::findOrFail($request->status_id);

            if ($newStatus->code === 'REJECTED' && !$request->filled('rejection_reason')) {
                return back()->withErrors([
                    'rejection_reason' => 'La razon de rechazo es requerida cuando el estatus es RECHAZADO.',
                ]);
            }

            $rejectedAt = $ticket->rejected_at;
            if ($newStatus->code === 'REJECTED' && !$ticket->rejected_at) {
                $rejectedAt = now();
            }

            $dataToUpdate = [
                'ticket_type_id' => $request->ticket_type_id,
                'category_id' => $request->category_id,
                'status_id' => $request->status_id,
                'priority_id' => $request->priority_id,
                'title' => $request->title,
                'description' => $request->description,
                'is_anonymous' => $request->is_anonymous,
                'rejected_at' => $rejectedAt,
            ];

            if ($request->has('rejection_reason')) {
                $dataToUpdate['rejection_reason'] = $request->rejection_reason;
            }

            $ticket->update($dataToUpdate);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('feedback/tickets', 'public');

                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'storage_disk' => 'public',
                        'uploaded_by_user_id' => Auth::id(),
                    ]);
                }
            }

            return back()->with('success', 'Ticket de feedback actualizado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar el ticket de feedback',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        try {
            $ticket = Ticket::findOrFail($id);
            $ticket->delete();

            return back()->with('success', 'Ticket de feedback eliminado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar el ticket de feedback',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function generateTicketNumber(): string {
        $year = now()->format('y');

        $lastTicket = Ticket::whereYear('created_at', now()->year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastTicket ? ((int) substr($lastTicket->ticket_number, -6)) + 1 : 1;

        return 'FB-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
