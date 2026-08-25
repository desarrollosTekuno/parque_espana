<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\CafeteriaVisit;
use App\Models\AdminClub\GuestListVariable;
use App\Services\AdminClub\CafeteriaCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use Inertia\Inertia;

class CafeteriaVisitController extends Controller
{
    private const DOCUMENT_TYPES = ['INE', 'pasaporte', 'licencia', 'credencial_trabajo', 'otro'];

    public function __construct()
    {
        $this->middleware('permission:cafeteria-visits.index')->only('index', 'open');
        $this->middleware('permission:cafeteria-visits.store')->only('store');
        $this->middleware('permission:cafeteria-visits.checkout')->only('checkout');
    }

    /**
     * Visitas de cafetería activas (aún no registran salida) del club dado.
     * Solo consulta — se usa en el módulo de Cobranza para elegir a quién
     * se le está registrando la salida (ver checkout()).
     */
    public function open(Request $request)
    {
        $clubId = (int) ($request->integer('club_id') ?: session('club_id'));

        $visits = CafeteriaVisit::where('club_id', $clubId)
            ->where('status', 'active')
            ->orderBy('expires_at')
            ->get(['id', 'visitor_name', 'document_type', 'document_number', 'entered_at', 'expires_at', 'min_consumption']);

        return response()->json($visits);
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';

        // Visitas activas — todas en pantalla, ordenadas por vencimiento próximo primero
        $activeVisits = CafeteriaVisit::where('club_id', $clubId)
            ->where('status', 'active')
            ->orderBy('expires_at', 'asc')
            ->get();

        // Historial paginado
        $historyQuery = CafeteriaVisit::where('club_id', $clubId)
            ->where('status', 'completed');

        if ($search = $request->input('history_search')) {
            $historyQuery->where(function ($q) use ($search, $like) {
                $q->where('visitor_name', $like, "%{$search}%")
                  ->orWhere('document_number', $like, "%{$search}%");
            });
        }

        $visitHistory = $historyQuery
            ->orderBy('checked_out_at', 'desc')
            ->paginate(
                $request->input('history_per_page', 10),
                ['*'],
                'history_page'
            )
            ->withQueryString();

        // Configuración vigente para mostrar en el frontend
        $cafeteriaConfig = [
            'min_consumption' => (float) (GuestListVariable::where('code', 'CAFETERIA_MIN_CONSUMPTION')
                ->where('club_id', $clubId)->value('value') ?? 300),
            'duration_hours'  => (int) (GuestListVariable::where('code', 'CAFETERIA_DURATION_HOURS')
                ->where('club_id', $clubId)->value('value') ?? 2),
        ];

        return Inertia::render('AdminClubs/CafeteriaVisits/Index', [
            'activeVisits'       => $activeVisits,
            'visitHistory'       => $visitHistory,
            'cafeteriaConfig'    => $cafeteriaConfig,
            'clubPaymentMethods' => $this->resolveClubPaymentMethods($clubId),
        ]);
    }

    protected function resolveClubPaymentMethods(int $clubId)
    {
        return ClubPaymentMethod::query()
            ->with('paymentMethod')
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(function ($cpm) {
                return [
                    'id'                    => $cpm->paymentMethod?->id,
                    'code'                  => $cpm->paymentMethod?->code,
                    'name'                  => $cpm->paymentMethod?->name,
                    'requires_reference'    => (bool) $cpm->paymentMethod?->requires_reference,
                    'requires_bank_name'    => (bool) $cpm->paymentMethod?->requires_bank_name,
                    'requires_check_number' => (bool) $cpm->paymentMethod?->requires_check_number,
                    'affects_cash_cut'      => (bool) $cpm->paymentMethod?->affects_cash_cut,
                ];
            })
            ->filter(fn ($m) => !empty($m['id']))
            ->values();
    }

    public function store(Request $request)
    {
        $clubId = (int) session('club_id');

        $request->validate([
            'visitor_name'    => 'required|string|max:200',
            'document_type'   => 'required|string|in:' . implode(',', self::DOCUMENT_TYPES),
            'document_number' => 'required|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $durationHours = (float) (GuestListVariable::where('code', 'CAFETERIA_DURATION_HOURS')
            ->where('club_id', $clubId)->value('value') ?? 2);

        $minConsumption = (float) (GuestListVariable::where('code', 'CAFETERIA_MIN_CONSUMPTION')
            ->where('club_id', $clubId)->value('value') ?? 300);

        $enteredAt = now()->setTimezone('America/Mexico_City');
        $expiresAt = $enteredAt->copy()->addHours($durationHours);

        //dd($enteredAt);

        $visit = CafeteriaVisit::create([
            'club_id'         => $clubId,
            'visitor_name'    => $request->visitor_name,
            'document_type'   => $request->document_type,
            'document_number' => $request->document_number,
            'entered_at'      => $enteredAt,
            'expires_at'      => $expiresAt,
            'min_consumption' => $minConsumption,
            'status'          => 'active',
            'notes'           => $request->notes,
            'registered_by'   => $request->user()?->id,
        ]);

        $message = 'Ingreso registrado. El visitante tiene hasta las ' .
            $expiresAt->format('H:i') . ' (' . (int) $durationHours . ' hora(s)).';

        if ($request->wantsJson() || $request->boolean('as_json')) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'visit' => [
                    'id' => $visit->id,
                    'visitor_name' => $visit->visitor_name,
                    'expires_at' => $visit->expires_at,
                    'min_consumption' => (float) $visit->min_consumption,
                ],
            ]);
        }

        return back()->with('success', $message);
    }
    public function checkout(Request $request, CafeteriaVisit $cafeteriaVisit, CafeteriaCheckoutService $checkoutService)
    {
        if ($cafeteriaVisit->status === 'completed') {
            return back()->withErrors(['messageError' => 'Este ingreso ya fue procesado.']);
        }

        $request->validate([
            'consumption_amount' => 'required|numeric|min:0',
        ]);

        $consumption    = (float) $request->consumption_amount;
        $minConsumption = (float) $cafeteriaVisit->min_consumption;
        $waived         = $consumption >= $minConsumption;

        $paymentMethod = null;

        if (!$waived) {
            $request->validate([
                'payment_method_id' => 'required|integer',
                'paid_at'           => 'required|date',
                'reference'         => 'nullable|string|max:200',
                'bank_name'         => 'nullable|string|max:200',
                'check_number'      => 'nullable|string|max:100',
            ]);

            $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

            $errors = [];
            if ($paymentMethod->requires_bank_name && blank($request->bank_name)) {
                $errors['bank_name'] = 'Debes capturar el banco del pago.';
            }
            if ($paymentMethod->requires_reference && blank($request->reference)) {
                $errors['reference'] = 'Debes capturar la referencia del pago.';
            }
            if ($paymentMethod->requires_check_number && blank($request->check_number)) {
                $errors['check_number'] = 'Debes capturar el número de cheque.';
            }
            if (!empty($errors)) {
                return back()->withErrors($errors);
            }
        }

        DB::beginTransaction();
        try {
            $charge = $checkoutService->checkout($cafeteriaVisit, $consumption, $request->user()?->id);

            if ($charge) {
                $payment = Payment::create([
                    'membership_account_id' => null,
                    'club_id'               => $cafeteriaVisit->club_id,
                    'payment_method_id'     => $paymentMethod->id,
                    'amount'                => $minConsumption,
                    'paid_at'               => $request->paid_at,
                    'reference'             => $request->reference,
                    'bank_name'             => $request->bank_name,
                    'check_number'          => $request->check_number,
                    'notes'                 => 'Cobro de acceso a cafetería para ' . $cafeteriaVisit->visitor_name,
                    'received_by'           => $request->user()?->id,
                    'status'                => 'registered',
                    'metadata'              => [
                        'cafeteria_visit_id' => $cafeteriaVisit->id,
                        'settlement_channel' => 'cashier',
                        'affects_cash_cut'   => $paymentMethod->affects_cash_cut,
                    ],
                ]);

                PaymentApplication::create([
                    'payment_id'     => $payment->id,
                    'charge_id'      => $charge->id,
                    'applied_amount' => $minConsumption,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $message = $waived
            ? 'Salida registrada. Acceso exento — consumo suficiente en cafetería.'
            : 'Salida registrada. Se cobró $' . number_format($minConsumption, 2) . ' de acceso al parque. Devolver identificación.';

        return back()->with('success', $message);
    }
}
