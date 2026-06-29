<?php

namespace App\Services\Billing;

use App\Models\Billing\AnnualDiscountRule;
use App\Models\Billing\Charge;
use App\Models\Billing\CreditBalance;
use App\Models\Billing\CreditMovement;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnnualPaymentService
{
    /**
     * Procesa un pago de anualidad:
     *  1. Aplica el pago a los cargos mensuales del año en orden (ene → dic).
     *  2. Si aplica una regla de descuento, genera saldo a favor.
     *  3. Si el descuento es de mes completo, aplica el crédito al mes libre (diciembre).
     *
     * @param MembershipAccount $account
     * @param int               $year    Año que cubre la anualidad.
     * @param Payment           $payment Pago ya registrado en la BD con el monto real cobrado.
     * @param int               $clubId  Club al que pertenecen los cargos.
     */
    public function processAnnualPayment(
        MembershipAccount $account,
        int $year,
        Payment $payment,
        int $clubId
    ): void {
        DB::transaction(function () use ($account, $year, $payment, $clubId) {
            // 1. Cargos de mensualidad pendientes del año, ordenados por mes
            $charges = Charge::query()
                ->with('concept')
                ->where('membership_account_id', $account->id)
                ->where('period_year', $year)
                ->whereIn('status', ['pending', 'partial'])
                ->whereHas('concept', fn ($q) => $q->where('code', 'MONTHLY_FEE'))
                ->whereHas('membership', fn ($q) => $q->where('club_id', $clubId))
                ->orderBy('period_month')
                ->get();

            if ($charges->isEmpty()) {
                return;
            }

            // 2. Regla de descuento aplicable según el mes en que se realiza el pago
            $paymentMonth = Carbon::parse($payment->paid_at)->month;
            $rule         = AnnualDiscountRule::findApplicable($year, $paymentMonth);

            // Cuota mensual de la membresía (base para el cálculo del crédito)
            $membership = Membership::where('membership_account_id', $account->id)
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->first();
            $monthlyFee = round((float) ($membership?->monthly_fee_share ?? $membership?->monthly_fee ?? 0), 2);

            $freeMonthCharge = null;
            $creditAmount    = 0.0;

            if ($rule && $monthlyFee > 0) {
                $creditAmount    = round($monthlyFee * (float) $rule->discount_months, 2);
                $freeMonthCharge = $charges->firstWhere('period_month', $rule->free_month);
            }

            // 3. Aplicar el pago a los cargos en orden
            $remaining = round((float) $payment->amount, 2);

            foreach ($charges as $charge) {
                if ($remaining <= 0) {
                    break;
                }

                $toApply = min($remaining, round((float) $charge->balance, 2));
                if ($toApply <= 0) {
                    continue;
                }

                PaymentApplication::create([
                    'payment_id'     => $payment->id,
                    'charge_id'      => $charge->id,
                    'applied_amount' => $toApply,
                ]);

                $newBalance = round((float) $charge->balance - $toApply, 2);
                $charge->update([
                    'balance' => $newBalance,
                    'status'  => $newBalance <= 0 ? 'paid' : 'partial',
                ]);

                $remaining = round($remaining - $toApply, 2);
            }

            // 4. Generar saldo a favor por el descuento
            if ($creditAmount > 0) {
                $creditBalance = CreditBalance::forAccount($account->id);
                $creditBalance->increment('amount', $creditAmount);

                CreditMovement::create([
                    'membership_account_id' => $account->id,
                    'amount'                => $creditAmount,
                    'concept'               => 'annual_discount',
                    'payment_id'            => $payment->id,
                    'notes'                 => "Descuento anualidad {$year} ({$rule->discount_months} mes(es) libres)",
                ]);

                // Si el descuento es mes completo, liquidar el mes libre con el crédito recién generado
                if ($rule->discount_months >= 1.0 && $freeMonthCharge) {
                    $fresh = $freeMonthCharge->fresh();
                    if ($fresh && $fresh->status !== 'paid') {
                        $this->applyCreditToCharge($account, $fresh);
                    }
                }
            }
        });
    }

    /**
     * Aplica saldo a favor de la cuenta a un cargo, solo si:
     *  - El crédito cubre el saldo completo del cargo.
     *  - El cargo no ha sido parcialmente pagado (el crédito no se mezcla con pagos en efectivo).
     *
     * @return bool  true si se aplicó el crédito, false si no se pudo aplicar.
     */
    public function applyCreditToCharge(MembershipAccount $account, Charge $charge): bool
    {
        $creditBalance = CreditBalance::forAccount($account->id);
        $chargeBalance = round((float) $charge->balance, 2);
        $chargeAmount  = round((float) $charge->amount, 2);

        // No aplicar si el cargo ya fue parcialmente pagado con otro método
        if ($chargeBalance <= 0 || abs($chargeBalance - $chargeAmount) > 0.01) {
            return false;
        }

        if (round((float) $creditBalance->amount, 2) < $chargeBalance) {
            return false;
        }

        DB::transaction(function () use ($creditBalance, $charge, $account, $chargeBalance) {
            $creditBalance->decrement('amount', $chargeBalance);

            CreditMovement::create([
                'membership_account_id' => $account->id,
                'amount'                => -$chargeBalance,
                'concept'               => 'applied_to_charge',
                'charge_id'             => $charge->id,
                'notes'                 => "Saldo a favor aplicado al cargo #{$charge->id}",
            ]);

            $charge->update([
                'balance' => 0,
                'status'  => 'paid',
            ]);
        });

        return true;
    }
}
