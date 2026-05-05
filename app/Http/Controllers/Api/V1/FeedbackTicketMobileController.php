<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Feedback\Status;
use App\Models\Feedback\Ticket;
use App\Models\Members\Member;
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

    public function store(Request $request, Club $club) {
        $request->validate([
            'ticket_type_id' => 'required',
            'category_id' => 'required',
            'priority_id' => 'required',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'is_anonymous' => 'nullable|boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

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

                if ($request->hasFile('attachments')) {
                    $this->storeTicketAttachments($ticket, $request->file('attachments'));
                }

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
}
