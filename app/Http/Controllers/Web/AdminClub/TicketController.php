<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Billing\Payment;
use App\Services\Billing\PaymentTicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TicketController extends Controller {

    public function __construct() {
        $this->middleware('permission:tickets.index')->only(['index', 'data']);
    }

    public function index(Request $request) {
        $prefix = 'tickets';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        $clubId = (int) ($request->club_id ?? session('club_id'));

        $query = Payment::query()
            ->whereNotNull('folio')
            ->with([
                'club',
                'receiver',
                'paymentMethod',
                'membershipAccount.primaryHolder.member',
            ]);

        if ($clubId) {
            $query->where('club_id', $clubId);
        }

        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function (Builder $builder) use ($search, $like) {
                $builder->where('folio', $like, "%{$search}%")
                    ->orWhereHas('membershipAccount', function (Builder $q) use ($search, $like) {
                        $q->where('membership_number', $like, "%{$search}%");
                    })
                    ->orWhereHas('membershipAccount.primaryHolder.member', function (Builder $q) use ($search, $like) {
                        $q->where('first_name', $like, "%{$search}%")
                            ->orWhere('last_name', $like, "%{$search}%")
                            ->orWhere('second_last_name', $like, "%{$search}%");
                    });
            });
        }

        $tickets = $query
            ->orderBy('paid_at', 'desc')
            ->paginate(
                $request->input("{$prefix}_per_page", 10),
                ['*'],
                "{$prefix}_page",
                $request->input("{$prefix}_page", 1)
            )
            ->through(function (Payment $payment) {
                $titular = null;

                if ($payment->membershipAccount) {
                    if ($payment->membershipAccount->primaryHolder) {
                        if ($payment->membershipAccount->primaryHolder->member) {
                            $titular = $payment->membershipAccount->primaryHolder->member->full_name;
                        }
                    }
                }

                return [
                    'id' => $payment->id,
                    'folio' => $payment->folio,
                    'fecha' => $payment->paid_at,
                    'cuenta_numero' => $payment->membershipAccount ? $payment->membershipAccount->membership_number : null,
                    'titular' => $titular,
                    'monto' => $payment->amount,
                    'forma_pago' => $payment->paymentMethod ? $payment->paymentMethod->name : null,
                    'cajero' => $payment->receiver ? $payment->receiver->name : null,
                    'club_nombre' => $payment->club ? $payment->club->name : null,
                ];
            })
            ->appends($request->all());

        return Inertia::render('AdminClubs/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $request->input("{$prefix}_search"),
            ],
        ]);
    }

    public function data(Payment $payment) {
        $servicio = new PaymentTicketService();

        $datos = $servicio->data($payment);

        return response()->json($datos);
    }
}
