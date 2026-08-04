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

    public function withTicketConcept(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            $concept = ChargeConcept::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (!$concept) {
                throw new RuntimeException('No hay conceptos activos para generar el desglose del ticket.');
            }

            $charge = Charge::query()->create([
                'membership_account_id' => $payment->membership_account_id,
                'membership_id' => null,
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
            ]);
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
            'membership_account_id' => $account->id,
            'club_id' => $clubId,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'reference' => 'TEST-TICKET-' . strtoupper($this->faker->unique()->bothify('####??')),
            'bank_name' => $paymentMethod->requires_bank_name ? 'Banco de prueba' : null,
            'check_number' => $paymentMethod->requires_check_number ? $this->faker->numerify('######') : null,
            'notes' => 'Pago generado para pruebas de impresión de tickets',
            'received_by' => $receiver?->id,
            'status' => 'registered',
            'metadata' => [
                'source' => 'payment-ticket-test-factory',
            ],
        ];
    }
}
