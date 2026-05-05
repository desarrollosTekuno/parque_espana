<?php

namespace App\Http\Controllers\Web\AdminClub\Feedback;

use App\Models\Feedback\Priority;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class FeedbackManagementController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-management.index')->only('index');
        $this->middleware('permission:feedback-management.update')->only('update');
    }

    public function index(Request $request) {
        $clubId = (int) ($request->club_id ?? session('club_id'));
        $driver = DB::getDriverName();

        $query = Ticket::with([
            'category',
            'status',
            'priority',
            'assignedTo',
            'attachments',
            'comments.user',
            'statusHistory.oldStatus',
            'statusHistory.newStatus',
            'statusHistory.changedBy',
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
            'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code', 'color']),
            'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code']),
            'filters' => [
                'search' => $request->input('search', ''),
                'status_id' => $request->input('status_id'),
                'priority_id' => $request->input('priority_id'),
            ],
        ]);
    }

    public function update(Request $request, Ticket $feedback) {
        return response()->json([
            'message' => 'Actualizacion de gestion de casos pendiente de implementacion',
        ]);
    }
}
