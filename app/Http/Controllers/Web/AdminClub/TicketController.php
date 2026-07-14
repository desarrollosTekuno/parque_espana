<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Billing\Payment;
use App\Services\Billing\PaymentTicketService;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class TicketController extends Controller {

    public function __construct() {
        $this->middleware('permission:tickets.index')->only(['index', 'data']);
    }

    public function index() {
        return Inertia::render('AdminClubs/Tickets/Index');
    }

    public function data(Payment $payment) {
        $servicio = new PaymentTicketService();

        $datos = $servicio->data($payment);

        return response()->json($datos);
    }
}
