<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\Payment;
use App\Models\Members\Member;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function pending(Request $request, Club $club): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->getMembershipAccount($request, $club);
        if ($result['_err']) {
            return $this->error($result['message'], $result['status']);
        }
        $membershipAccount = $result['account'];

        $perPage = (int) $request->input('per_page', 15);

        $total = Charge::where('membership_account_id', $membershipAccount->id)
            ->whereIn('status', ['pending', 'partial'])
            ->sum('balance');

        $charges = Charge::with('concept')
            ->where('membership_account_id', $membershipAccount->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->simplePaginate($perPage);

        return $this->ok([
            'currency' => 'MXN',
            'total'    => round((float) $total, 2),
            'count'    => $charges->count(),
            'items'    => $charges->getCollection()->map(fn ($c) => $this->formatCharge($c))->values(),
            'meta'     => [
                'current_page'   => $charges->currentPage(),
                'per_page'       => $charges->perPage(),
                'has_more_pages' => $charges->hasMorePages(),
            ],
        ]);
    }

    public function history(Request $request, Club $club): JsonResponse
    {
        $request->validate([
            'search'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to'   => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->getMembershipAccount($request, $club);

        if ($result['_err']) {
            return $this->error($result['message'], $result['status']);
        }

        $membershipAccount = $result['account'];

        $perPage = (int) $request->input('per_page', 15);

        $payments = Payment::with([
            'paymentMethod',
            'applications.charge.concept',
        ])
            ->where('membership_account_id', $membershipAccount->id)
            ->where('club_id', $club->id)
            ->when(
                $request->filled('date_from'),
                fn ($q) => $q->whereDate(
                    'paid_at',
                    '>=',
                    $request->date_from
                )
            )
            ->when(
                $request->filled('date_to'),
                fn ($q) => $q->whereDate(
                    'paid_at',
                    '<=',
                    $request->date_to
                )
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where(
                        'reference',
                        'like',
                        '%' . $request->search . '%'
                    )
                    ->orWhereHas(
                        'paymentMethod',
                        fn ($m) => $m->where(
                            'name',
                            'like',
                            '%' . $request->search . '%'
                        )
                    )
                    ->orWhereHas(
                        'applications.charge.concept',
                        fn ($c) => $c->where(
                            'name',
                            'like',
                            '%' . $request->search . '%'
                        )
                    );
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage);

        return $this->ok([
            'currency' => 'MXN',

            'count' => $payments->count(),

            'items' => $payments->getCollection()
                ->map(fn ($p) => $this->formatPayment($p))
                ->values(),

            'filters' => [
                'search'    => $request->input('search'),
                'date_from' => $request->input('date_from'),
                'date_to'   => $request->input('date_to'),
            ],

            'meta' => [
                'current_page'   => $payments->currentPage(),
                'per_page'       => $payments->perPage(),
                'has_more_pages' => $payments->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, Club $club, int $payment): JsonResponse
    {
        $result = $this->getMembershipAccount($request, $club);
        if ($result['_err']) {
            return $this->error($result['message'], $result['status']);
        }
        $membershipAccount = $result['account'];

        $charge = Charge::with('concept')
            ->where('membership_account_id', $membershipAccount->id)
            ->where('id', $payment)
            ->first();

        if ($charge) {
            return $this->ok($this->formatCharge($charge));
        }

        $paidPayment = Payment::with(['paymentMethod', 'applications.charge.concept'])
            ->where('membership_account_id', $membershipAccount->id)
            ->where('club_id', $club->id)
            ->where('id', $payment)
            ->first();

        if (!$paidPayment) {
            return $this->notFound('Pago o cargo no encontrado.');
        }

        return $this->ok($this->formatPayment($paidPayment, true));
    }

    public function receipt(Request $request, Club $club, Payment $payment): JsonResponse
    {
        $result = $this->getMembershipAccount($request, $club);
        if ($result['_err']) {
            return $this->error($result['message'], $result['status']);
        }
        $membershipAccount = $result['account'];

        if ((int) $payment->membership_account_id !== (int) $membershipAccount->id
            || (int) $payment->club_id !== (int) $club->id) {
            return $this->notFound('Pago no encontrado.');
        }

        return $this->ok([
            'payment_id'        => $payment->id,
            'receipt_available' => false,
            'url'               => null,
            'message'           => 'El comprobante descargable aún no está disponible.',
        ]);
    }

    public function monthlyFees(Request $request, Club $club): JsonResponse
    {
        $request->validate([
            'year'     => ['sometimes', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->getMembershipAccount($request, $club);
        if ($result['_err']) {
            return $this->error($result['message'], $result['status']);
        }
        $membershipAccount = $result['account'];

        $year    = (int) $request->input('year', now()->year);
        $perPage = (int) $request->input('per_page', 15);

        $charges = Charge::with('concept')
            ->where('membership_account_id', $membershipAccount->id)
            ->where('period_year', $year)
            ->whereHas('concept', fn ($q) => $q->where('code', 'MONTHLY_FEE'))
            ->orderBy('period_month')
            ->orderBy('id')
            ->simplePaginate($perPage);

        return $this->ok([
            'year'     => $year,
            'currency' => 'MXN',
            'items'    => $charges->getCollection()->map(fn ($c) => $this->formatCharge($c))->values(),
            'meta'     => [
                'current_page'   => $charges->currentPage(),
                'per_page'       => $charges->perPage(),
                'has_more_pages' => $charges->hasMorePages(),
            ],
        ]);
    }

    private function getMembershipAccount(Request $request, Club $club): array
    {
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return ['_err' => true, 'status' => 404, 'message' => 'No se encontró un perfil de socio asociado a este usuario.'];
        }

        $accountMember = $member->accountMemberships()
            ->with('membershipAccount')
            ->whereHas('membershipAccount.memberships', fn ($q) => $q
                ->where('club_id', $club->id)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$accountMember) {
            return ['_err' => true, 'status' => 403, 'message' => 'No tienes una membresía activa en este club.'];
        }

        if (!$accountMember->is_primary_holder) {
            return ['_err' => true, 'status' => 403, 'message' => 'Solo el socio titular puede consultar pagos.'];
        }

        return ['_err' => false, 'account' => $accountMember->membershipAccount];
    }

    private function formatCharge(Charge $charge): array
    {
        return [
            'id'                      => $charge->id,
            'type'                    => 'charge',
            'concept'                 => $charge->concept?->name,
            'concept_code'            => $charge->concept?->code,
            'description'             => $charge->description,
            'period_year'             => $charge->period_year,
            'period_month'            => $charge->period_month,
            'issue_date'              => $charge->issue_date,
            'due_date'                => $charge->due_date,
            'currency'                => 'MXN',
            'amount'                  => $charge->amount,
            'balance'                 => $charge->balance,
            'status'                  => $this->chargeStatus($charge),
            'allows_partial_payments' => (bool) $charge->allows_partial_payments,
        ];
    }

    private function formatPayment(Payment $payment, bool $includeApplications = false): array {
        $data = [
            'id'                => $payment->id,
            'type'              => 'payment',
            'reference'         => $payment->reference,
            'payment_method'    => $payment->paymentMethod?->name,
            'currency'          => 'MXN',
            'amount'            => $payment->amount,
            'paid_at'           => $payment->paid_at,
            'receipt_available' => false,

            'concepts' => $payment->applications
                ->map(fn ($a) => [
                    'concept' => $a->charge?->concept?->name,
                    'amount'  => $a->applied_amount,
                ])
                ->values(),
        ];

        if ($includeApplications) {
            $data['applications'] = $payment->applications
                ->map(fn ($a) => [
                    'charge_id' => $a->charge_id,
                    'concept'   => $a->charge?->concept?->name,
                    'amount'    => $a->applied_amount,
                ])
                ->values();
        }

        return $data;
    }

    private function chargeStatus(Charge $charge): string
    {
        if (in_array($charge->status, ['pending', 'partial'])
            && $charge->due_date
            && Carbon::parse($charge->due_date)->lt(today())) {
            return 'overdue';
        }

        return $charge->status;
    }
}
