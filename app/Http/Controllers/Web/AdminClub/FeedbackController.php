<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Feedback\Ticket;
use App\Models\Feedback\Category;
use App\Models\Feedback\TicketType;
use App\Models\Feedback\Status;
use App\Models\Feedback\Priority;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback.index')->only('index');
        $this->middleware('permission:feedback.store')->only('store');
        $this->middleware('permission:feedback.update')->only('update');
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
            ])->where('club_id', $clubId);

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

    public function store(Request $request) {
        $request->validate([
            'ticket_type_id' => 'required|exists:feedback.ticket_types,id',
            'category_id' => 'required|exists:feedback.categories,id',
            'priority_id' => 'required|exists:feedback.priorities,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'is_anonymous' => 'required|boolean',
        ], [
            'ticket_type_id.required' => 'Debes seleccionar un tipo.',
            'category_id.required' => 'Debes seleccionar una categoría.',
            'priority_id.required' => 'Debes seleccionar una prioridad.',
            'title.required' => 'Debes ingresar un título.',
            'description.required' => 'Debes ingresar una descripción.',
        ]);

        try {
            $status = Status::where('code', 'submitted')->firstOrFail();

            Ticket::create([
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
                'is_anonymous' => $request->is_anonymous,
                'submitted_at' => now(),
            ]);

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
            'ticket_type_id' => 'required|exists:feedback.ticket_types,id',
            'category_id' => 'required|exists:feedback.categories,id',
            'status_id' => 'required|exists:feedback.statuses,id',
            'priority_id' => 'required|exists:feedback.priorities,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'is_anonymous' => 'required|boolean',
        ], [
            'ticket_type_id.required' => 'Debes seleccionar un tipo.',
            'category_id.required' => 'Debes seleccionar una categoría.',
            'status_id.required' => 'Debes seleccionar un estatus.',
            'priority_id.required' => 'Debes seleccionar una prioridad.',
            'title.required' => 'Debes ingresar un título.',
            'description.required' => 'Debes ingresar una descripción.',
        ]);

        try {
            $feedback->update([
                'ticket_type_id' => $request->ticket_type_id,
                'category_id' => $request->category_id,
                'status_id' => $request->status_id,
                'priority_id' => $request->priority_id,
                'member_id' => $request->member_id,
                'assigned_to_user_id' => $request->assigned_to_user_id,
                'title' => $request->title,
                'description' => $request->description,
                'resolution_notes' => $request->resolution_notes,
                'is_anonymous' => $request->is_anonymous,
            ]);

            return back()->with('success', 'Queja o sugerencia actualizada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la queja o sugerencia',
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

    private function generateTicketNumber(): string
    {
        $year = now()->format('y');

        $lastTicket = Ticket::whereYear('created_at', now()->year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastTicket ? ((int) substr($lastTicket->ticket_number, -6)) + 1 : 1;

        return 'FB-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
