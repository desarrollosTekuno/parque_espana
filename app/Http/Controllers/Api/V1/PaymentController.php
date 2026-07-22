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

        $account = $this->getMembershipAccount($request, $club);

        if (!$account['success']) {
            return response()->json($account, $account['status']);
        }

        $membershipAccount = $account['data'];
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

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => 'MXN',
                'total' => round((float) $total, 2),
                'count' => $charges->count(),
                'items' => $charges->getCollection()->map(fn ($charge) => $this->formatCharge($charge))->values(),
                'meta' => [
                    'current_page' => $charges->currentPage(),
                    'per_page' => $charges->perPage(),
                    'has_more_pages' => $charges->hasMorePages(),
                ],
            ],
        ]);
    }

    public function history(Request $request, Club $club): JsonResponse
    {
        $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $account = $this->getMembershipAccount($request, $club);

        if (!$account['success']) {
            return response()->json($account, $account['status']);
        }

        $membershipAccount = $account['data'];
        $perPage = (int) $request->input('per_page', 15);

        $payments = Payment::with('paymentMethod')
            ->where('membership_account_id', $membershipAccount->id)
            ->where('club_id', $club->id)
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('paid_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('paid_at', '<=', $request->date_to))
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('reference', 'like', '%' . $request->search . '%')
                        ->orWhereHas('paymentMethod', fn ($method) => $method->where('name', 'like', '%' . $request->search . '%'));
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => 'MXN',
                'count' => $payments->count(),
                'items' => $payments->getCollection()->map(fn ($payment) => $this->formatPayment($payment))->values(),
                'filters' => [
                    'search' => $request->input('search'),
                    'date_from' => $request->input('date_from'),
                    'date_to' => $request->input('date_to'),
                ],
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'has_more_pages' => $payments->hasMorePages(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Club $club, int $payment): JsonResponse
    {
        $account = $this->getMembershipAccount($request, $club);

        if (!$account['success']) {
            return response()->json($account, $account['status']);
        }

        $membershipAccount = $account['data'];

        $charge = Charge::with('concept')
            ->where('membership_account_id', $membershipAccount->id)
            ->where('id', $payment)
            ->first();

        if ($charge) {
            return response()->json([
                'success' => true,
                'data' => $this->formatCharge($charge),
            ]);
        }

        $paidPayment = Payment::with(['paymentMethod', 'applications.charge.concept'])
            ->where('membership_account_id', $membershipAccount->id)
            ->where('club_id', $club->id)
            ->where('id', $payment)
            ->first();

        if (!$paidPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Pago o cargo no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPayment($paidPayment, true),
        ]);
    }

    public function receipt(Request $request, Club $club, Payment $payment): JsonResponse
    {
        $account = $this->getMembershipAccount($request, $club);

        if (!$account['success']) {
            return response()->json($account, $account['status']);
        }

        $membershipAccount = $account['data'];

        if ((int) $payment->membership_account_id !== (int) $membershipAccount->id || (int) $payment->club_id !== (int) $club->id) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->id,
                'receipt_available' => false,
                'url' => null,
                'message' => 'El comprobante descargable aun no esta disponible.',
            ],
        ]);
    }

    public function monthlyFees(Request $request, Club $club): JsonResponse
    {
        $request->validate([
            'year' => ['sometimes', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $account = $this->getMembershipAccount($request, $club);

        if (!$account['success']) {
            return response()->json($account, $account['status']);
        }

        $membershipAccount = $account['data'];
        $year = (int) $request->input('year', now()->year);
        $perPage = (int) $request->input('per_page', 15);

        $charges = Charge::with('concept')
            ->where('membership_account_id', $membershipAccount->id)
            ->where('period_year', $year)
            ->whereHas('concept', fn ($query) => $query->where('code', 'MONTHLY_FEE'))
            ->orderBy('period_month')
            ->orderBy('id')
            ->simplePaginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'currency' => 'MXN',
                'items' => $charges->getCollection()->map(fn ($charge) => $this->formatCharge($charge))->values(),
                'meta' => [
                    'current_page' => $charges->currentPage(),
                    'per_page' => $charges->perPage(),
                    'has_more_pages' => $charges->hasMorePages(),
                ],
            ],
        ]);
    }

    private function getMembershipAccount(Request $request, Club $club): array
    {
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'No se encontro un perfil de socio asociado a este usuario.',
            ];
        }

        $accountMember = $member->accountMemberships()
            ->with('membershipAccount')
            ->whereHas('membershipAccount.memberships', fn ($query) => $query
                ->where('club_id', $club->id)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$accountMember) {
            return [
                'success' => false,
                'status' => 403,
                'message' => 'No tienes una membresia activa en este club.',
            ];
        }

        if (!$accountMember->is_primary_holder) {
            return [
                'success' => false,
                'status' => 403,
                'message' => 'Solo el socio titular puede consultar pagos.',
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'data' => $accountMember->membershipAccount,
        ];
    }

    private function formatCharge(Charge $charge): array
    {
        return [
            'id' => $charge->id,
            'type' => 'charge',
            'concept' => $charge->concept?->name,
            'concept_code' => $charge->concept?->code,
            'description' => $charge->description,
            'period_year' => $charge->period_year,
            'period_month' => $charge->period_month,
            'issue_date' => $charge->issue_date,
            'due_date' => $charge->due_date,
            'currency' => 'MXN',
            'amount' => $charge->amount,
            'balance' => $charge->balance,
            'status' => $this->chargeStatus($charge),
            'allows_partial_payments' => (bool) $charge->allows_partial_payments,
        ];
    }

    private function formatPayment(Payment $payment, bool $includeApplications = false): array
    {
        $data = [
            'id' => $payment->id,
            'type' => 'payment',
            'reference' => $payment->reference,
            'payment_method' => $payment->paymentMethod?->name,
            'currency' => 'MXN',
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at,
            'receipt_available' => false,
        ];

        if ($includeApplications) {
            $data['applications'] = $payment->applications->map(fn ($application) => [
                'charge_id' => $application->charge_id,
                'concept' => $application->charge?->concept?->name,
                'amount' => $application->applied_amount,
            ])->values();
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
