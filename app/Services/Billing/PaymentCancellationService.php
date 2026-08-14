<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use App\Models\Memberships\Membership;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCancellationService
{
    private const BOUNCED_CHECK_COMMISSION_CONCEPT_CODE = 'COMISION_CHEQUE_REBOTADO';
    private const BOUNCED_CHECK_SURCHARGE_RATE = 0.10;

    /**
     * Cancela un pago (una forma de pago específica — ver
     * Payment::payment_group_id para las demás del mismo cobro, que NO se
     * tocan aquí) y lo marca como cancelado.
     *
     * - Cancelación normal (no cheque rebotado): revierte el efecto que tuvo
     *   sobre los cargos que cubrió (regresa balance/status) — no queda
     *   ningún registro de que se haya cobrado.
     * - Cheque rebotado: el cargo original (p. ej. la mensualidad) se deja
     *   TAL CUAL estaba (no se revierte) — la deuda no se resucita ahí, se
     *   re-cobra mediante dos cargos NUEVOS sobre la misma cuenta: uno por el
     *   monto original que rebotó (concepto CHEQUE_REBOTADO) y otro por el
     *   10% de recargo (concepto COMISION_CHEQUE_REBOTADO). Revertir el cargo
     *   original A LA VEZ que se crea el nuevo dejaría la deuda duplicada.
     *
     * @return array{payment: Payment, bounced_check_charge: ?Charge, bounced_check_commission_charge: ?Charge}
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

        return DB::transaction(fn () => $this->applyCancellation($payment, $reason, $cancelledBy, $isBouncedCheck));
    }

    /**
     * Cancela TODAS las formas de pago de un mismo cobro agrupado
     * (Payment::payment_group_id) en una sola transacción — el "rollback"
     * completo del ticket: cada pago del grupo revierte lo que aplicó a sus
     * cargos, tal como si el cobro nunca se hubiera registrado. No admite
     * cheque rebotado aquí (eso es una cancelación parcial de una sola forma
     * de pago, ver cancel()) — si alguna línea del grupo es un cheque
     * rebotado, se debe cancelar individualmente con cancel().
     *
     * @param  Collection<int, Payment>  $payments
     * @return array{payments: Collection<int, Payment>}
     */
    public function cancelGroup(Collection $payments, string $reason, ?int $cancelledBy): array
    {
        $pending = $payments->reject(fn (Payment $payment) => $payment->status === 'cancelled');

        if ($pending->isEmpty()) {
            throw ValidationException::withMessages([
                'payment' => 'Todas las formas de pago de este ticket ya están canceladas.',
            ]);
        }

        return DB::transaction(function () use ($pending, $reason, $cancelledBy) {
            $cancelled = $pending->map(
                fn (Payment $payment) => $this->applyCancellation($payment, $reason, $cancelledBy, false)['payment']
            );

            return ['payments' => $cancelled];
        });
    }

    private function applyCancellation(
        Payment $payment,
        string $reason,
        ?int $cancelledBy,
        bool $isBouncedCheck
    ): array {
        $now = now();

        $payment->load('applications.charge');

        $bouncedCheckCharge = null;
        $commissionCharge = null;

        if ($isBouncedCheck) {
            $membership = $this->resolveBouncedCheckTargetMembership($payment);
            $bouncedCheckCharge = $this->createBouncedCheckCharge($payment, $membership, $now);
            $commissionCharge = $this->createBouncedCheckCommissionCharge($payment, $membership, $now);
        } else {
            foreach ($payment->applications as $application) {
                $this->reverseApplication($application);
            }
        }

        $payment->update([
            'status' => 'cancelled',
            'cancelled_at' => $now,
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason,
        ]);

        return [
            'payment' => $payment->fresh(),
            'bounced_check_charge' => $bouncedCheckCharge,
            'bounced_check_commission_charge' => $commissionCharge,
        ];
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

    /**
     * El cargo de cheque rebotado siempre se crea en la MISMA cuenta donde
     * se cobró el pago (membership_account_id) — igual que el dinero, que
     * "se registra completo en este parque" aunque una de las formas de
     * pago representara al otro (ver resolveBouncedCheckConceptCode: el
     * CONCEPTO sí distingue de qué parque era el cheque, CHEQUE_REBOTADO_PARQUE1
     * vs PARQUE2, pero el cargo se queda donde se está llevando la cuenta del
     * socio, no se mueve a la cuenta del otro parque).
     *
     * No se puede tomar la membresía del cargo original que se revirtió (ver
     * reverseApplication), porque ese cargo puede pertenecer a la membresía
     * del OTRO parque cuando se trata de una mensualidad combo interclub (se
     * factura sobre lo que esté facturable en ese momento, no necesariamente
     * la de esta cuenta).
     */
    private function resolveBouncedCheckTargetMembership(Payment $payment): ?Membership
    {
        return Membership::query()
            ->where('membership_account_id', $payment->membership_account_id)
            ->where('is_primary', true)
            ->first();
    }

    private function resolveConceptOrFail(string $code, string $label): ChargeConcept
    {
        $concept = ChargeConcept::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$concept) {
            throw ValidationException::withMessages([
                'payment' => "No existe el concepto de {$label} ({$code}) en el catálogo.",
            ]);
        }

        return $concept;
    }

    /**
     * El concepto de cheque rebotado es distinto por parque (CHEQUE_REBOTADO_PARQUE1
     * / CHEQUE_REBOTADO_PARQUE2, según club_id) — el parque es el del PAGO
     * que se está cancelando (dónde se cobró el cheque), no el de la
     * membresía del cargo original (que puede ser de otro parque en una
     * mensualidad combo interclub).
     */
    private function resolveBouncedCheckConceptCode(Payment $payment): string
    {
        $clubId = $payment->metadata['represents_club_id'] ?? $payment->club_id;

        return 'CHEQUE_REBOTADO_PARQUE' . $clubId;
    }

    private function resolveChargeMemberId(Payment $payment, ?Membership $membership): ?int
    {
        return $membership?->account?->primaryHolder?->member_id
            ?? $payment->applications->first()?->charge?->member_id;
    }

    /**
     * Cargo por el monto ORIGINAL que rebotó (100%, sin recargo) — vuelve a
     * cobrar la deuda que el pago cancelado dejó de cubrir. El recargo del
     * 10% va aparte, en createBouncedCheckCommissionCharge.
     */
    private function createBouncedCheckCharge(Payment $payment, ?Membership $membership, Carbon $now): Charge
    {
        $concept = $this->resolveConceptOrFail($this->resolveBouncedCheckConceptCode($payment), 'cheque rebotado');
        $amount = round((float) $payment->amount, 2);

        return Charge::create([
            'membership_account_id' => $membership?->membership_account_id ?? $payment->membership_account_id,
            'membership_id' => $membership?->id,
            'member_id' => $this->resolveChargeMemberId($payment, $membership),
            'concept_id' => $concept->id,
            'description' => sprintf(
                'Cheque rebotado%s — vuelve a cobrarse el monto original',
                $payment->folio ? " ({$payment->folio})" : " (pago #{$payment->id})"
            ),
            'amount' => $amount,
            'balance' => $amount,
            'issue_date' => $now->toDateString(),
            'due_date' => $now->toDateString(),
            'allows_partial_payments' => false,
            'status' => 'pending',
            'metadata' => [
                'concept_code' => $concept->code,
                'origin' => 'bounced_check',
                'original_payment_id' => $payment->id,
                'original_amount' => (float) $payment->amount,
            ],
        ]);
    }

    /**
     * Cargo por el 10% de recargo del cheque rebotado, como concepto
     * independiente (COMISION_CHEQUE_REBOTADO) — separado del monto original
     * para que quede claro contablemente cuál es la deuda re-cobrada y cuál
     * es la penalización.
     */
    private function createBouncedCheckCommissionCharge(Payment $payment, ?Membership $membership, Carbon $now): Charge
    {
        $concept = $this->resolveConceptOrFail(self::BOUNCED_CHECK_COMMISSION_CONCEPT_CODE, 'comisión de cheque rebotado');
        $commissionAmount = round((float) $payment->amount * self::BOUNCED_CHECK_SURCHARGE_RATE, 2);

        return Charge::create([
            'membership_account_id' => $membership?->membership_account_id ?? $payment->membership_account_id,
            'membership_id' => $membership?->id,
            'member_id' => $this->resolveChargeMemberId($payment, $membership),
            'concept_id' => $concept->id,
            'description' => sprintf(
                'Comisión por cheque rebotado%s — 10%% de $%s',
                $payment->folio ? " ({$payment->folio})" : " (pago #{$payment->id})",
                number_format((float) $payment->amount, 2)
            ),
            'amount' => $commissionAmount,
            'balance' => $commissionAmount,
            'issue_date' => $now->toDateString(),
            'due_date' => $now->toDateString(),
            'allows_partial_payments' => false,
            'status' => 'pending',
            'metadata' => [
                'concept_code' => $concept->code,
                'origin' => 'bounced_check_commission',
                'original_payment_id' => $payment->id,
                'original_amount' => (float) $payment->amount,
                'surcharge_rate' => self::BOUNCED_CHECK_SURCHARGE_RATE,
            ],
        ]);
    }
}
