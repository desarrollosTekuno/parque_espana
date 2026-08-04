<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Billing\Payment;
use App\Services\Billing\PaymentTicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller {

    public function __construct(private PaymentTicketService $ticketService) {
        $this->middleware('permission:tickets.index')->only(['index', 'data']);
    }

    public function index(Request $request): Response {
        $clubId = (int) session('club_id');

        abort_if(!$clubId, 403);

        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';
        $search = trim((string) $request->input('tickets_search', ''));

        $query = Payment::query()
            ->where('club_id', $clubId)
            ->whereNotNull('folio')
            ->with([
                'receiver',
                'paymentMethod',
                'membershipAccount.primaryHolder.member',
            ]);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search, $like) {
                $builder->where('folio', $like, "%{$search}%")
                    ->orWhere('reference', $like, "%{$search}%")
                    ->orWhereHas('membershipAccount', function (Builder $accountQuery) use ($search, $like) {
                        $accountQuery->where('membership_number', $like, "%{$search}%")
                            ->orWhere('internal_account_number', $like, "%{$search}%");
                    })
                    ->orWhereHas('membershipAccount.primaryHolder.member', function (Builder $memberQuery) use ($search, $like) {
                        $memberQuery->where('first_name', $like, "%{$search}%")
                            ->orWhere('last_name', $like, "%{$search}%")
                            ->orWhere('second_last_name', $like, "%{$search}%");
                    });
            });
        }

        $tickets = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(
                $request->integer('tickets_per_page', 10),
                ['*'],
                'tickets_page',
                $request->integer('tickets_page', 1)
            )
            ->through(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'folio' => $payment->folio,
                    'fecha' => $payment->paid_at,
                    'estatus' => $payment->status,
                    'cuenta_numero' => $payment->membershipAccount?->membership_number,
                    'cuenta_interna' => $payment->membershipAccount?->internal_account_number,
                    'titular' => $payment->membershipAccount?->primaryHolder?->member?->full_name,
                    'monto' => (float) $payment->amount,
                    'forma_pago' => $payment->paymentMethod?->name,
                    'cajero' => $payment->receiver?->name,
                    'cajero_codigo' => $payment->receiver?->code,
                ];
            })
            ->appends($request->all());

        return Inertia::render('AdminClubs/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function data(Payment $payment): JsonResponse {
        $clubId = (int) session('club_id');

        if (!$clubId || (int) $payment->club_id !== $clubId) {
            abort(404);
        }

        return response()->json($this->ticketService->data($payment));
    }
}
