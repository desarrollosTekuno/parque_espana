<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Services\Billing\PaymentCancellationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentCancellationController extends Controller
{
    public function __construct(private PaymentCancellationService $cancellationService)
    {
        $this->middleware('permission:payments.cancel');
    }

    public function create(Payment $payment): Response
    {
        $clubId = (int) session('club_id');

        abort_if((int) $payment->club_id !== $clubId, 404);

        if ($payment->status === 'cancelled') {
            return Inertia::render('AdminClubs/Payments/Cancel', [
                'payment' => null,
                'alreadyCancelled' => true,
            ]);
        }

        $payment->load([
            'paymentMethod',
            'receiver',
            'membershipAccount.primaryHolder.member',
            'applications.charge.concept',
        ]);

        return Inertia::render('AdminClubs/Payments/Cancel', [
            'payment' => [
                'id' => $payment->id,
                'folio' => $payment->folio,
                'paid_at' => $payment->paid_at,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'bank_name' => $payment->bank_name,
                'check_number' => $payment->check_number,
                'metodo_pago' => $payment->paymentMethod?->name,
                'metodo_pago_codigo' => $payment->paymentMethod?->code,
                'metodo_pago_clave' => $this->resolveInternalKey($payment),
                'cajero' => $payment->receiver?->name,
                'cuenta_numero' => $payment->membershipAccount?->membership_number,
                'titular' => $payment->membershipAccount?->primaryHolder?->member?->full_name,
                'is_split' => (bool) $payment->payment_group_id
                    && Payment::where('payment_group_id', $payment->payment_group_id)->count() > 1,
                'conceptos' => $payment->applications->map(fn ($application) => [
                    'charge_id' => $application->charge_id,
                    'concepto' => $application->charge?->concept?->name,
                    'monto_aplicado' => (float) $application->applied_amount,
                ])->values(),
            ],
            'alreadyCancelled' => false,
        ]);
    }

    public function groupCreate(string $paymentGroupId): Response
    {
        $clubId = (int) session('club_id');

        $payments = Payment::query()
            ->where('payment_group_id', $paymentGroupId)
            ->where('club_id', $clubId)
            ->with(['paymentMethod', 'receiver', 'membershipAccount.primaryHolder.member', 'applications.charge.concept'])
            ->orderBy('id')
            ->get();

        abort_if($payments->isEmpty(), 404);

        $allCancelled = $payments->every(fn (Payment $payment) => $payment->status === 'cancelled');
        $first = $payments->first();

        return Inertia::render('AdminClubs/Payments/CancelGroup', [
            'group' => [
                'payment_group_id' => $paymentGroupId,
                'folio' => $first->folio,
                'paid_at' => $first->paid_at,
                'total' => (float) $payments->sum('amount'),
                'cuenta_numero' => $first->membershipAccount?->membership_number,
                'titular' => $first->membershipAccount?->primaryHolder?->member?->full_name,
                'cajero' => $first->receiver?->name,
                'payments' => $payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'metodo_pago' => $payment->paymentMethod?->name,
                    'metodo_pago_codigo' => $this->resolveInternalKey($payment),
                    'amount' => (float) $payment->amount,
                    'reference' => $payment->reference,
                    'bank_name' => $payment->bank_name,
                    'check_number' => $payment->check_number,
                    'status' => $payment->status,
                    'conceptos' => $payment->applications->map(fn ($application) => [
                        'charge_id' => $application->charge_id,
                        'concepto' => $application->charge?->concept?->name,
                        'monto_aplicado' => (float) $application->applied_amount,
                    ])->values(),
                ])->values(),
            ],
            'alreadyCancelled' => $allCancelled,
        ]);
    }

    public function groupStore(Request $request, string $paymentGroupId)
    {
        $clubId = (int) session('club_id');

        $payments = Payment::query()
            ->where('payment_group_id', $paymentGroupId)
            ->where('club_id', $clubId)
            ->get();

        abort_if($payments->isEmpty(), 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'also_cancel_charge' => ['sometimes', 'boolean'],
            'confirmed' => ['accepted'],
        ], [
            'reason.required' => 'Indica el motivo de la cancelación.',
            'confirmed.accepted' => 'Debes confirmar la cancelación antes de continuar.',
        ]);

        try {
            $this->cancellationService->cancelGroup(
                payments: $payments,
                reason: $validated['reason'],
                cancelledBy: auth()->id(),
                alsoCancelCharge: (bool) ($validated['also_cancel_charge'] ?? false)
            );

            return redirect()->route('tickets.index')->with('success', 'Ticket cancelado correctamente.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Error al cancelar ticket agrupado', [
                'payment_group_id' => $paymentGroupId,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'messageError' => 'Ocurrió un error al cancelar el ticket. Intente de nuevo.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * La clave interna real de un método de pago es por parque (ver
     * billing.club_payment_methods.internal_key, p. ej. CHP1/CHP2 para
     * cheque) — se resuelve contra el parque que la línea representa
     * (metadata.represents_club_id en pagos cruzados de un cobro dividido,
     * o el club_id del pago). Si no hay configuración, cae al code genérico
     * del catálogo (billing.payment_methods.code).
     */
    private function resolveInternalKey(Payment $payment): ?string
    {
        if (!$payment->payment_method_id) {
            return $payment->paymentMethod?->code;
        }

        $clubId = $payment->metadata['represents_club_id'] ?? $payment->club_id;

        $internalKey = ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->where('payment_method_id', $payment->payment_method_id)
            ->value('internal_key');

        return $internalKey ?: $payment->paymentMethod?->code;
    }

    public function store(Request $request, Payment $payment)
    {
        $clubId = (int) session('club_id');

        abort_if((int) $payment->club_id !== $clubId, 404);

        if ($payment->status === 'cancelled') {
            return back()->withErrors([
                'messageError' => 'Este pago ya está cancelado.',
                'exception' => '',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'is_bounced_check' => ['sometimes', 'boolean'],
            'also_cancel_charge' => ['sometimes', 'boolean'],
            'confirmed' => ['accepted'],
        ], [
            'reason.required' => 'Indica el motivo de la cancelación.',
            'confirmed.accepted' => 'Debes confirmar la cancelación antes de continuar.',
        ]);

        $isBouncedCheck = (bool) ($validated['is_bounced_check'] ?? false);
        $alsoCancelCharge = (bool) ($validated['also_cancel_charge'] ?? false);

        if ($isBouncedCheck && strtoupper((string) $payment->paymentMethod?->code) !== 'CHECK') {
            return back()->withErrors([
                'is_bounced_check' => 'Solo se puede marcar como cheque rebotado un pago hecho con cheque.',
            ]);
        }

        if ($isBouncedCheck && $alsoCancelCharge) {
            return back()->withErrors([
                'also_cancel_charge' => 'No puedes marcar cheque rebotado y cancelar el cargo al mismo tiempo.',
            ]);
        }

        try {
            $result = $this->cancellationService->cancel(
                payment: $payment,
                reason: $validated['reason'],
                cancelledBy: auth()->id(),
                isBouncedCheck: $isBouncedCheck,
                alsoCancelCharge: $alsoCancelCharge
            );

            $message = match (true) {
                (bool) $result['bounced_check_charge'] => 'Pago cancelado. Se generaron los cargos de cheque rebotado y su comisión.',
                $alsoCancelCharge => 'Pago y cargo cancelados correctamente.',
                default => 'Pago cancelado correctamente.',
            };

            return redirect()->route('tickets.index')->with('success', $message);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Error al cancelar pago', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'messageError' => 'Ocurrió un error al cancelar el pago. Intente de nuevo.',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
