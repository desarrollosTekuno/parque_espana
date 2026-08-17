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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnualPaymentService
{
    /**
     * Procesa un pago de anualidad:
     *  1. Aplica los pagos (puede ser más de uno — cobro dividido en varias
     *     formas de pago, ver CollectionController::storeAnnualPayment) a
     *     los cargos mensuales pendientes de CUALQUIER año hasta $year
     *     inclusive (adeudo de años anteriores + todo lo que falte del año
     *     en curso), en orden cronológico y en el orden en que llegan las
     *     formas de pago (waterfall, igual que
     *     PaymentRegistrationService::registerSplit).
     *  2. Si aplica una regla de descuento, genera saldo a favor.
     *  3. Si el descuento es de mes completo, aplica el crédito al mes libre (diciembre).
     *
     * El descuento SIEMPRE se calcula sobre la cuota de $year (no se
     * prorratea sobre adeudo de años anteriores, que se cobra completo) —
     * ver CollectionController::storeAnnualPayment.
     *
     * @param MembershipAccount   $account    Cuenta sobre la que se registra el saldo a favor.
     * @param array               $accountIds Cuentas del socio a considerar para juntar los cargos
     *                                        (la propia y, si aplica, las de su combo interclub —
     *                                        ver CollectionController::resolveGroupAccountIds).
     * @param int                 $year       Año hasta el que se cubre la anualidad (inclusive).
     * @param Collection<int, Payment> $payments Pagos ya registrados en la BD (uno por forma de
     *                                        pago), en el orden en que se deben aplicar.
     * @param ?AnnualDiscountRule $rule       Regla YA resuelta por el llamador (ver
     *                                        CollectionController::resolveAnnualDiscountPaymentMonth) — no
     *                                        se vuelve a calcular aquí a partir de $payments->first()->paid_at,
     *                                        porque en un pago de diciembre que adelanta el año siguiente el mes
     *                                        calendario crudo del pago (diciembre) no es el mes que hay que
     *                                        usar para buscar la regla (mes 0, "antes de que empiece el año") —
     *                                        recalcularlo aquí con el mes crudo daba una regla distinta (o
     *                                        ninguna) a la que ya se le había cobrado al socio.
     */
    public function processAnnualPayment(
        MembershipAccount $account,
        array $accountIds,
        int $year,
        Collection $payments,
        ?AnnualDiscountRule $rule = null
    ): void {
        DB::transaction(function () use ($account, $accountIds, $year, $payments, $rule) {
            // 1. Cargos de mensualidad pendientes de este año o anteriores,
            // ordenados cronológicamente (más viejo primero).
            $charges = Charge::query()
                ->with('concept')
                ->whereIn('membership_account_id', $accountIds)
                ->where('period_year', '<=', $year)
                ->whereIn('status', ['pending', 'partial'])
                ->whereHas('concept', fn ($q) => $q->where('code', 'MONTHLY_FEE'))
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->get();

            if ($charges->isEmpty()) {
                return;
            }

            $firstPayment = $payments->first();

            // 2. La regla de descuento ya viene resuelta por el llamador — ver
            // el docblock de este método.

            // Cuota mensual de la membresía facturable (base para el cálculo
            // del crédito) — la del propio año $year, sin importar en qué
            // cuenta del grupo viva (combo interclub).
            $membership = Membership::query()
                ->whereIn('membership_account_id', $accountIds)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->where('is_billable', true)
                ->first();
            $monthlyFee = round((float) ($membership?->monthly_fee_share ?? $membership?->monthly_fee ?? 0), 2);

            $freeMonthCharge = null;
            $creditAmount    = 0.0;

            if ($rule && $monthlyFee > 0) {
                $creditAmount    = round($monthlyFee * (float) $rule->discount_months, 2);
                // El mes libre siempre es de $year (el año que se está
                // cubriendo con la anualidad) — con adeudo de años
                // anteriores mezclado en $charges, un firstWhere sin filtrar
                // el año podía emparejar diciembre del año viejo por error.
                $freeMonthCharge = $charges->first(
                    fn (Charge $charge) => (int) $charge->period_month === (int) $rule->free_month
                        && (int) $charge->period_year === $year
                );
            }

            // 3. Aplicar los pagos a los cargos en orden — waterfall: cada
            // cargo se cubre con la(s) forma(s) de pago necesarias, en el
            // orden en que llegaron (igual que registerSplit, pero sin la
            // prioridad de reparto entre parques: aquí todo es MONTHLY_FEE
            // en orden cronológico).
            $remainingPerPayment = $payments->map(fn (Payment $p) => round((float) $p->amount, 2))->all();

            foreach ($charges as $charge) {
                $remainingForCharge = round((float) $charge->balance, 2);

                foreach ($payments as $index => $payment) {
                    if ($remainingForCharge <= 0.001) {
                        break;
                    }
                    if ($remainingPerPayment[$index] <= 0.001) {
                        continue;
                    }

                    $toApply = round(min($remainingForCharge, $remainingPerPayment[$index]), 2);

                    PaymentApplication::create([
                        'payment_id'     => $payment->id,
                        'charge_id'      => $charge->id,
                        'applied_amount' => $toApply,
                    ]);

                    $remainingPerPayment[$index] = round($remainingPerPayment[$index] - $toApply, 2);
                    $remainingForCharge = round($remainingForCharge - $toApply, 2);
                }

                $newBalance = round($remainingForCharge, 2);
                $charge->update([
                    'balance' => $newBalance,
                    'status'  => $newBalance <= 0 ? 'paid' : 'partial',
                ]);
            }

            // 4. Generar saldo a favor por el descuento
            if ($creditAmount > 0) {
                $creditBalance = CreditBalance::forAccount($account->id);
                $creditBalance->increment('amount', $creditAmount);

                CreditMovement::create([
                    'membership_account_id' => $account->id,
                    'amount'                => $creditAmount,
                    'concept'               => 'annual_discount',
                    'payment_id'            => $firstPayment->id,
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
