<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Billing\Payment;
use App\Services\Billing\PaymentTicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(private PaymentTicketService $ticketService)
    {
        $this->middleware('permission:tickets.index')->only(['index', 'data']);
    }

    public function index(Request $request): Response
    {
        $clubId = (int) session('club_id');

        abort_if(! $clubId, 403);

        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';
        $search = trim((string) $request->input('tickets_search', ''));

        $baseQuery = Payment::query()->where('club_id', $clubId);

        if ($search !== '') {
            $baseQuery->where(function (Builder $builder) use ($search, $like) {
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

        $perPage = $request->integer('tickets_per_page', 10);
        $page = $request->integer('tickets_page', 1);

        // Un cobro dividido en varias formas de pago genera varios Payment
        // (uno por forma, ver PaymentRegistrationService::registerSplit)
        // que comparten payment_group_id — aquí se colapsan a UN renglón
        // por cobro en vez de uno por Payment. Se agrupa en PHP (no con
        // GROUP BY en SQL) porque payment_group_id puede venir null en
        // pagos de antes de este campo, y cada uno de esos cuenta como su
        // propio grupo de un solo elemento.
        $matches = (clone $baseQuery)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get(['id', 'payment_group_id']);

        $orderedGroupKeys = $matches
            ->map(fn (Payment $p) => $p->payment_group_id ?: ('single-'.$p->id))
            ->unique()
            ->values();

        $total = $orderedGroupKeys->count();
        $pageKeys = $orderedGroupKeys->forPage($page, $perPage)->values();

        $realGroupIds = $pageKeys->filter(fn (string $k) => ! str_starts_with($k, 'single-'))->values();
        $singleIds = $pageKeys->filter(fn (string $k) => str_starts_with($k, 'single-'))
            ->map(fn (string $k) => (int) substr($k, strlen('single-')))
            ->values();

        $groupPayments = Payment::query()
            ->where('club_id', $clubId)
            ->where(function (Builder $q) use ($realGroupIds, $singleIds) {
                $q->whereIn('payment_group_id', $realGroupIds)
                    ->orWhereIn('id', $singleIds);
            })
            ->with(['receiver', 'paymentMethod', 'membershipAccount.primaryHolder.member'])
            ->get()
            ->groupBy(fn (Payment $p) => $p->payment_group_id ?: ('single-'.$p->id));

        $tickets = $pageKeys
            ->map(function (string $key) use ($groupPayments) {
                $payments = $groupPayments->get($key, collect());
                $representative = $payments->sortBy('id')->first();

                if (! $representative) {
                    return null;
                }

                return [
                    'id' => $representative->id,
                    'payment_group_id' => $representative->payment_group_id,
                    'folio' => $representative->folio,
                    'formas_de_pago_count' => $payments->count(),
                    'fecha' => $representative->paid_at,
                    'estatus' => $representative->status,
                    'cuenta_numero' => $representative->membershipAccount?->membership_number,
                    'cuenta_interna' => $representative->membershipAccount?->internal_account_number,
                    'titular' => $representative->membershipAccount?->primaryHolder?->member?->full_name,
                    'monto' => round((float) $payments->sum('amount'), 2),
                    'forma_pago' => $payments->count() > 1
                        ? $payments->count().' formas de pago'
                        : $representative->paymentMethod?->name,
                    'cajero' => $representative->receiver?->name,
                    'cajero_codigo' => $representative->receiver?->name
                        ? strtoupper(Str::ascii(mb_substr(trim($representative->receiver->name), 0, 1)))
                        : null,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('AdminClubs/Tickets/Index', [
            'tickets' => [
                'data' => $tickets,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function data(Payment $payment): JsonResponse
    {
        $clubId = (int) session('club_id');

        if (! $clubId || (int) $payment->club_id !== $clubId) {
            abort(404);
        }

        return response()->json([
            'payment_group_key' => $payment->payment_group_id ?: ('single-'.$payment->id),
            'tickets' => $this->ticketService->tickets($payment),
        ]);
    }
}
