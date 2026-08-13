<?php

namespace Database\Factories\Billing;

use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Administrator\UserClub;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $account = MembershipAccount::query()
            ->whereNotNull('club_id')
            ->inRandomOrder()
            ->first();

        if (!$account) {
            throw new RuntimeException('No hay cuentas de membresía disponibles para generar pagos de prueba.');
        }

        return $this->attributesForClub((int) $account->club_id, $account);
    }

    public function forClub(int $clubId): static
    {
        return $this->state(function () use ($clubId) {
            $account = MembershipAccount::query()
                ->where('club_id', $clubId)
                ->inRandomOrder()
                ->first();

            if (!$account) {
                throw new RuntimeException("El club {$clubId} no tiene cuentas para generar pagos de prueba.");
            }

            return $this->attributesForClub($clubId, $account);
        });
    }

    public function forAccount(MembershipAccount $account): static
    {
        return $this->state(function () use ($account) {
            return $this->attributesForClub((int) $account->club_id, $account);
        });
    }

    public function usingPaymentMethod(string $code): static
    {
        return $this->state(function () use ($code) {
            $paymentMethod = PaymentMethod::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (!$paymentMethod) {
                throw new RuntimeException("No se encontró el método de pago {$code}.");
            }

            $bankName = null;

            if (in_array($code, ['CREDIT_CARD', 'DEBIT_CARD'], true)) {
                $bankName = 'VISA';
            } elseif (in_array($code, ['BANK_TRANSFER', 'CHECK'], true)) {
                $bankName = 'Banco de prueba';
            }

            return [
                'payment_method_id' => $paymentMethod->id,
                'reference' => 'TEST-TICKET-' . strtoupper($this->faker->unique()->bothify('####??')),
                'bank_name' => $bankName,
                'check_number' => $code === 'CHECK' ? $this->faker->numerify('######') : null,
            ];
        });
    }

    public function withTicketConcept(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            $concept = ChargeConcept::query()
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN code = 'MONTHLY_FEE' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->first();

            if (!$concept) {
                throw new RuntimeException('No hay conceptos activos para generar el desglose del ticket.');
            }

            $breakdown = $this->taxBreakdown((float) $payment->amount, $concept, (int) $payment->club_id);
            $membershipId = $payment->membershipAccount
                ?->memberships()
                ->where('club_id', $payment->club_id)
                ->where('status', 'active')
                ->where('is_primary', true)
                ->value('id');

            $charge = Charge::query()->create([
                'membership_account_id' => $payment->membership_account_id,
                'membership_id' => $membershipId,
                'member_id' => null,
                'concept_id' => $concept->id,
                'description' => 'Concepto de prueba para impresión de ticket',
                'amount' => $payment->amount,
                'balance' => 0,
                'issue_date' => $payment->paid_at?->toDateString(),
                'due_date' => $payment->paid_at?->toDateString(),
                'period_year' => null,
                'period_month' => null,
                'allows_partial_payments' => false,
                'status' => 'paid',
                'metadata' => [
                    'source' => 'payment-ticket-test-factory',
                ],
            ]);

            PaymentApplication::query()->create([
                'payment_id' => $payment->id,
                'charge_id' => $charge->id,
                'applied_amount' => $payment->amount,
                'subtotal' => $breakdown['subtotal'],
                'iva' => $breakdown['iva'],
            ]);

            $payment->update([
                'subtotal' => $breakdown['subtotal'],
                'iva' => $breakdown['iva'],
            ]);
        });
    }

    public function withTestFolio(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            $this->assignTestFolio($payment);
        });
    }

    public function splitWith(string $paymentMethodCode = 'DEBIT_CARD'): static
    {
        return $this->afterCreating(function (Payment $payment) use ($paymentMethodCode) {
            $application = $payment->applications()->with('charge.concept')->first();
            $paymentMethod = PaymentMethod::query()
                ->where('code', $paymentMethodCode)
                ->where('is_active', true)
                ->first();

            if (!$application || !$paymentMethod) {
                throw new RuntimeException('No fue posible generar el pago dividido de prueba.');
            }

            $groupId = $payment->payment_group_id ?: (string) Str::uuid();
            $total = round((float) $payment->amount, 2);
            $firstAmount = round($total * 0.4, 2);
            $secondAmount = round($total - $firstAmount, 2);
            $firstBreakdown = $this->taxBreakdown($firstAmount, $application->charge->concept, (int) $payment->club_id);
            $secondBreakdown = $this->taxBreakdown($secondAmount, $application->charge->concept, (int) $payment->club_id);
            $metadata = array_merge($payment->metadata ?? [], ['split_payment' => true]);

            $payment->update([
                'payment_group_id' => $groupId,
                'amount' => $firstAmount,
                'subtotal' => $firstBreakdown['subtotal'],
                'iva' => $firstBreakdown['iva'],
                'metadata' => $metadata,
            ]);
            $application->update([
                'applied_amount' => $firstAmount,
                'subtotal' => $firstBreakdown['subtotal'],
                'iva' => $firstBreakdown['iva'],
            ]);

            $secondPayment = Payment::query()->create([
                'payment_group_id' => $groupId,
                'membership_account_id' => $payment->membership_account_id,
                'club_id' => $payment->club_id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $secondAmount,
                'subtotal' => $secondBreakdown['subtotal'],
                'iva' => $secondBreakdown['iva'],
                'paid_at' => $payment->paid_at,
                'reference' => 'TEST-TICKET-SPLIT-' . strtoupper($this->faker->unique()->bothify('####??')),
                'bank_name' => in_array($paymentMethodCode, ['CREDIT_CARD', 'DEBIT_CARD'], true) ? 'VISA' : 'Banco de prueba',
                'check_number' => $paymentMethodCode === 'CHECK' ? $this->faker->numerify('######') : null,
                'notes' => 'Pago dividido generado para pruebas de impresión de tickets',
                'received_by' => $payment->received_by,
                'status' => 'registered',
                'metadata' => $metadata,
            ]);

            PaymentApplication::query()->create([
                'payment_id' => $secondPayment->id,
                'charge_id' => $application->charge_id,
                'applied_amount' => $secondAmount,
                'subtotal' => $secondBreakdown['subtotal'],
                'iva' => $secondBreakdown['iva'],
            ]);

            $this->assignTestFolio($secondPayment);
        });
    }

    private function attributesForClub(int $clubId, MembershipAccount $account): array
    {
        $clubPaymentMethod = ClubPaymentMethod::query()
            ->with('paymentMethod')
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        $paymentMethod = $clubPaymentMethod?->paymentMethod
            ?? PaymentMethod::query()->where('is_active', true)->inRandomOrder()->first();

        if (!$paymentMethod) {
            throw new RuntimeException('No hay métodos de pago activos para generar pagos de prueba.');
        }

        $receiverId = UserClub::query()
            ->where('club_id', $clubId)
            ->inRandomOrder()
            ->value('user_id');
        $receiver = User::query()->find($receiverId) ?? User::query()->first();

        return [
            'payment_group_id' => (string) Str::uuid(),
            'membership_account_id' => $account->id,
            'club_id' => $clubId,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'subtotal' => null,
            'iva' => null,
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'reference' => 'TEST-TICKET-' . strtoupper($this->faker->unique()->bothify('####??')),
            'bank_name' => $paymentMethod->requires_bank_name ? 'Banco de prueba' : null,
            'check_number' => $paymentMethod->requires_check_number ? $this->faker->numerify('######') : null,
            'notes' => 'Pago generado para pruebas de impresión de tickets',
            'received_by' => $receiver?->id,
            'status' => 'registered',
            'metadata' => [
                'session_club_id' => $clubId,
                'settlement_channel' => $paymentMethod->affects_cash_cut ? 'cashier' : 'services',
                'affects_cash_cut' => (bool) $paymentMethod->affects_cash_cut,
                'park_split' => null,
                'source' => 'payment-ticket-test-factory',
            ],
        ];
    }

    private function taxBreakdown(float $amount, ChargeConcept $concept, int $clubId): array
    {
        if ($concept->resolveAppliesIvaForClub($clubId)) {
            $subtotal = round(($amount * 100) / 116, 2);

            return [
                'subtotal' => $subtotal,
                'iva' => round(($subtotal * 16) / 100, 2),
            ];
        }

        return [
            'subtotal' => round($amount, 2),
            'iva' => 0.0,
        ];
    }

    private function assignTestFolio(Payment $payment): void
    {
        $cashierCode = $payment->receiver?->code ?: 'TEST';
        $clubCode = $payment->club?->code ?: 'CLUB' . $payment->club_id;
        $date = $payment->paid_at?->format('ymd') ?? now()->format('ymd');
        $consecutive = str_pad((string) $payment->id, 3, '0', STR_PAD_LEFT);

        $payment->update([
            'folio' => $clubCode . '-' . $cashierCode . '-' . $date . '-' . $consecutive,
        ]);
    }
}
