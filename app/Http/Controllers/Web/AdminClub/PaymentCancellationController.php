<?php

namespace App\Http\Controllers\Web\AdminClub;

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
            'confirmed' => ['accepted'],
        ], [
            'reason.required' => 'Indica el motivo de la cancelación.',
            'confirmed.accepted' => 'Debes confirmar la cancelación antes de continuar.',
        ]);

        $isBouncedCheck = (bool) ($validated['is_bounced_check'] ?? false);

        if ($isBouncedCheck && strtoupper((string) $payment->paymentMethod?->code) !== 'CHECK') {
            return back()->withErrors([
                'is_bounced_check' => 'Solo se puede marcar como cheque rebotado un pago hecho con cheque.',
            ]);
        }

        try {
            $result = $this->cancellationService->cancel(
                payment: $payment,
                reason: $validated['reason'],
                cancelledBy: auth()->id(),
                isBouncedCheck: $isBouncedCheck
            );

            $message = $result['penalty_charge']
                ? 'Pago cancelado. Se generó el cargo por cheque rebotado.'
                : 'Pago cancelado correctamente.';

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
