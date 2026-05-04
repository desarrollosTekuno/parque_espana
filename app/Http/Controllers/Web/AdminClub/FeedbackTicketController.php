<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Feedback\Category;
use App\Models\Feedback\Priority;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use App\Models\Feedback\TicketType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeedbackTicketController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-tickets.index')->only('index');
    }

    public function index(Request $request) {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();

            $query = Ticket::with(['type', 'category', 'status', 'priority'])
                ->where('club_id', $clubId);

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
}
