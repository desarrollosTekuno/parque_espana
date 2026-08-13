<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCancellationService
{
    private const BOUNCED_CHECK_CONCEPT_CODE = 'CR';
    private const BOUNCED_CHECK_SURCHARGE_RATE = 0.10;

    /**
     * Cancela un pago (una forma de pago específica — ver
     * Payment::payment_group_id para las demás del mismo cobro, que NO se
     * tocan aquí): revierte el efecto que tuvo sobre los cargos que cubrió
     * (regresa balance/status) y marca el pago como cancelado. Si
     * $isBouncedCheck es true, además genera el cargo de cheque rebotado
     * (monto original + 10%) sobre la misma cuenta.
     *
     * @return array{payment: Payment, penalty_charge: ?Charge}
     */
    public function cancel(
        Payment $payment,
        string $reason,
        ?int $cancelledBy,
        bool $isBouncedCheck = false
    ): array {
        if ($payment->status === 'cancelled') {
            throw ValidationException::withMessages([
                'payment' => 'Este pago ya está cancelado.',
            ]);
        }

        return DB::transaction(function () use ($payment, $reason, $cancelledBy, $isBouncedCheck) {
            $now = now();

            $payment->load('applications.charge');

            foreach ($payment->applications as $application) {
                $this->reverseApplication($application);
            }

            $penaltyCharge = $isBouncedCheck
                ? $this->createBouncedCheckCharge($payment, $now)
                : null;

            $payment->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
            ]);

            return [
                'payment' => $payment->fresh(),
                'penalty_charge' => $penaltyCharge,
            ];
        });
    }

    /**
     * Regresa a un cargo lo que este pago le había aplicado — solo si sigue
     * en 'paid'/'partial' (si mientras tanto se condonó o canceló por otro
     * lado, se deja como está, no se resucita). El balance no puede quedar
     * por encima del monto original del cargo, por si hubiera alguna
     * inconsistencia previa.
     */
    private function reverseApplication($application): void
    {
        $charge = $application->charge;

        if (!$charge || !in_array($charge->status, ['paid', 'partial'], true)) {
            return;
        }

        $restoredBalance = min(
            round((float) $charge->balance + (float) $application->applied_amount, 2),
            (float) $charge->amount
        );

        $charge->update([
            'balance' => $restoredBalance,
            'status' => $restoredBalance >= (float) $charge->amount ? 'pending' : 'partial',
        ]);
    }

    private function createBouncedCheckCharge(Payment $payment, Carbon $now): Charge
    {
        $concept = ChargeConcept::query()
            ->where('code', self::BOUNCED_CHECK_CONCEPT_CODE)
            ->where('is_active', true)
            ->first();

        if (!$concept) {
            throw ValidationException::withMessages([
                'payment' => 'No existe el concepto de cheque rebotado (CR) en el catálogo.',
            ]);
        }

        $penaltyAmount = round((float) $payment->amount * (1 + self::BOUNCED_CHECK_SURCHARGE_RATE), 2);
        $referenceCharge = $payment->applications->first()?->charge;

        return Charge::create([
            'membership_account_id' => $payment->membership_account_id,
            'membership_id' => $referenceCharge?->membership_id,
            'member_id' => $referenceCharge?->member_id,
            'concept_id' => $concept->id,
            'description' => sprintf(
                'Cheque rebotado%s — cargo original $%s + 10%%',
                $payment->folio ? " ({$payment->folio})" : " (pago #{$payment->id})",
                number_format((float) $payment->amount, 2)
            ),
            'amount' => $penaltyAmount,
            'balance' => $penaltyAmount,
            'issue_date' => $now->toDateString(),
            'due_date' => $now->toDateString(),
            'allows_partial_payments' => false,
            'status' => 'pending',
            'metadata' => [
                'concept_code' => $concept->code,
                'origin' => 'bounced_check',
                'original_payment_id' => $payment->id,
                'original_amount' => (float) $payment->amount,
                'surcharge_rate' => self::BOUNCED_CHECK_SURCHARGE_RATE,
            ],
        ]);
    }
}
