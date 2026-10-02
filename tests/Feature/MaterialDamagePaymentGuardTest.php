<?php

namespace Tests\Feature;

use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use App\Services\Billing\MaterialDamagePaymentGuard;
use App\Services\Billing\PaymentCancellationService;
use Database\Seeders\MaterialDamageConceptSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pruebas del cargo por daños materiales (concepto CD).
 *
 * IMPORTANTE: estas pruebas usan la base local PostgreSQL (PARQUES), porque
 * el sistema usa schemas (billing., memberships.) que SQLite no soporta.
 * Cada prueba corre dentro de una transacción que se deshace al terminar,
 * así que no dejan datos en la base.
 *
 * Cómo correrlas (PowerShell):
 *   $env:DB_CONNECTION='pgsql'; $env:DB_DATABASE='PARQUES'; php artisan test --filter=MaterialDamagePaymentGuardTest
 *
 * Si se corren con la configuración normal de phpunit.xml (SQLite), se saltan.
 *
 * Datos que se usan: un usuario con cuenta en ambos parques (mismo
 * account_group_id), una cuenta en el parque 1 y otra en el parque 2.
 */
class MaterialDamagePaymentGuardTest extends TestCase
{
    use DatabaseTransactions;

    private MaterialDamagePaymentGuard $guard;

    /** Cuenta del usuario en el parque 1. */
    private MembershipAccount $accountPark1;

    /** Cuenta del mismo usuario en el parque 2. */
    private MembershipAccount $accountPark2;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Estas pruebas requieren la base local PostgreSQL (ver comentario de la clase).');
        }

        // Asegura que el concepto CD exista con los datos del seeder.
        $this->seed(MaterialDamageConceptSeeder::class);

        $this->guard = app(MaterialDamagePaymentGuard::class);

        [$this->accountPark1, $this->accountPark2] = $this->findUserWithAccountsInBothParks();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. Regla del guard
    // ─────────────────────────────────────────────────────────────────────

    public function test_sin_cd_pendiente_se_puede_pagar_cualquier_cosa(): void
    {
        $monthlyCharge = $this->createCharge($this->accountPark1, 'MONTHLY_FEE', 500);

        $this->guard->ensureCanPay($this->accountPark1->id, [
            ['charge_id' => $monthlyCharge->id, 'amount' => 500],
        ], true);

        $this->assertTrue(true, 'No debió lanzar error.');
    }

    public function test_cd_pendiente_bloquea_conceptos_nuevos(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 1200);

        $this->assertPaymentIsBlocked(function () use ($damageCharge) {
            $this->guard->ensureCanPay($this->accountPark1->id, [
                ['charge_id' => $damageCharge->id, 'amount' => 1200],
            ], true);
        });
    }

    public function test_cd_pendiente_bloquea_cobro_sin_cargos_como_la_anualidad(): void
    {
        $this->createCharge($this->accountPark1, 'CD', 1200);

        $this->assertPaymentIsBlocked(function () {
            $this->guard->ensureCanPay($this->accountPark1->id);
        });
    }

    public function test_cd_pendiente_bloquea_pagar_otro_cargo(): void
    {
        $this->createCharge($this->accountPark1, 'CD', 1200);
        $monthlyCharge = $this->createCharge($this->accountPark1, 'MONTHLY_FEE', 500);

        $this->assertPaymentIsBlocked(function () use ($monthlyCharge) {
            $this->guard->ensureCanPay($this->accountPark1->id, [
                ['charge_id' => $monthlyCharge->id, 'amount' => 500],
            ]);
        });
    }

    public function test_cd_pendiente_bloquea_mezclar_cd_con_otro_cargo(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 1200);
        $monthlyCharge = $this->createCharge($this->accountPark1, 'MONTHLY_FEE', 500);

        $this->assertPaymentIsBlocked(function () use ($damageCharge, $monthlyCharge) {
            $this->guard->ensureCanPay($this->accountPark1->id, [
                ['charge_id' => $damageCharge->id, 'amount' => 1200],
                ['charge_id' => $monthlyCharge->id, 'amount' => 500],
            ]);
        });
    }

    public function test_cd_pendiente_bloquea_pago_parcial(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 1200);

        $this->assertPaymentIsBlocked(function () use ($damageCharge) {
            $this->guard->ensureCanPay($this->accountPark1->id, [
                ['charge_id' => $damageCharge->id, 'amount' => 600],
            ]);
        });
    }

    public function test_cd_se_puede_pagar_solo_y_completo(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 1200.50);

        $this->guard->ensureCanPay($this->accountPark1->id, [
            ['charge_id' => $damageCharge->id, 'amount' => '1200.50'],
        ]);

        $this->assertTrue(true, 'No debió lanzar error.');
    }

    public function test_cd_de_un_parque_bloquea_los_cobros_del_otro_parque(): void
    {
        // El CD se registra en el parque 1...
        $this->createCharge($this->accountPark1, 'CD', 1200);
        $monthlyChargePark2 = $this->createCharge($this->accountPark2, 'MONTHLY_FEE', 500);

        // ...y el usuario intenta pagar en el parque 2.
        $this->assertPaymentIsBlocked(function () use ($monthlyChargePark2) {
            $this->guard->ensureCanPay($this->accountPark2->id, [
                ['charge_id' => $monthlyChargePark2->id, 'amount' => 500],
            ]);
        });
    }

    public function test_cd_ya_pagado_ya_no_bloquea(): void
    {
        $this->createCharge($this->accountPark1, 'CD', 1200, 'paid');

        $this->guard->ensureCanPay($this->accountPark1->id, [], true);

        $this->assertTrue(true, 'No debió lanzar error.');
    }

    public function test_cd_sigue_bloqueando_aunque_se_elimine_el_concepto_del_catalogo(): void
    {
        $this->createCharge($this->accountPark1, 'CD', 1200);

        ChargeConcept::query()->where('code', 'CD')->first()->delete();

        $this->assertPaymentIsBlocked(function () {
            $this->guard->ensureCanPay($this->accountPark1->id, [], true);
        });
    }

    public function test_related_account_ids_regresa_las_cuentas_de_ambos_parques(): void
    {
        $accountIds = $this->guard->relatedAccountIds($this->accountPark1->id);

        $this->assertContains($this->accountPark1->id, $accountIds);
        $this->assertContains($this->accountPark2->id, $accountIds);
    }

    public function test_related_account_ids_de_cuenta_sin_grupo_regresa_solo_esa_cuenta(): void
    {
        $accountWithoutGroup = MembershipAccount::query()->whereNull('account_group_id')->first();

        if ($accountWithoutGroup === null) {
            $this->markTestSkipped('No hay cuentas sin grupo en la base local.');
        }

        $this->assertSame([$accountWithoutGroup->id], $this->guard->relatedAccountIds($accountWithoutGroup->id));
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Cancelación de pagos del CD
    // ─────────────────────────────────────────────────────────────────────

    public function test_no_se_puede_cancelar_el_cargo_cd_al_cancelar_su_pago(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 800);
        $payment = $this->payDamageChargeFromCollections($damageCharge);

        try {
            app(PaymentCancellationService::class)->cancel($payment, 'Prueba', null, false, true);
            $this->fail('Se esperaba que no se pudiera cancelar el cargo CD.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no se puede cancelar', collect($e->errors())->flatten()->first());
        }

        // Nada cambió: el pago sigue registrado y el CD sigue pagado.
        $this->assertSame('registered', $payment->fresh()->status);
        $this->assertSame('paid', $damageCharge->fresh()->status);
    }

    public function test_cancelar_solo_el_pago_regresa_el_cd_a_pendiente(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 800);
        $payment = $this->payDamageChargeFromCollections($damageCharge);

        app(PaymentCancellationService::class)->cancel($payment, 'Prueba', null);

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('pending', $damageCharge->fresh()->status);
        $this->assertEquals(800, (float) $damageCharge->fresh()->balance);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Pantallas (peticiones HTTP como las manda el navegador)
    // ─────────────────────────────────────────────────────────────────────

    public function test_cobranza_registra_el_cd_como_cargo_pendiente(): void
    {
        $response = $this->asCashierOfClub($this->accountPark1->club_id)
            ->postJson(route('collections.material-damage.store'), [
                'membership_account_id' => $this->accountPark1->id,
                'amount' => 950.75,
                'description' => 'Vidrio roto en vestidores',
            ]);

        $response->assertOk();

        $charge = Charge::query()
            ->where('membership_account_id', $this->accountPark1->id)
            ->whereHas('concept', fn ($query) => $query->where('code', 'CD'))
            ->latest('id')
            ->first();

        $this->assertNotNull($charge);
        $this->assertSame('pending', $charge->status);
        $this->assertEquals(950.75, (float) $charge->balance);
        $this->assertSame('Vidrio roto en vestidores', $charge->description);
    }

    public function test_cobranza_muestra_el_cd_del_otro_parque(): void
    {
        $this->createCharge($this->accountPark1, 'CD', 1200);

        // El cajero está en el parque 2 y busca la cuenta del parque 2.
        $response = $this->asCashierOfClub($this->accountPark2->club_id)
            ->getJson(route('collections.search', ['query' => $this->accountPark2->membership_number]));

        $response->assertOk();

        $conceptCodes = collect($response->json('pending_concepts'))->pluck('concept_code');
        $this->assertContains('CD', $conceptCodes->all(), 'El CD del parque 1 debe verse en el parque 2.');
    }

    public function test_cobranza_bloquea_cobrar_otro_concepto_con_cd_pendiente(): void
    {
        $this->createCharge($this->accountPark1, 'CD', 1200);
        $monthlyConcept = ChargeConcept::query()->where('code', 'MONTHLY_FEE')->first();

        $response = $this->asCashierOfClub($this->accountPark2->club_id)
            ->postJson(route('collections.payment.store'), [
                'membership_account_id' => $this->accountPark2->id,
                'club_id' => $this->accountPark2->club_id,
                'paid_at' => now()->toDateString(),
                'payments' => [['payment_method_id' => $this->cashMethodId(), 'amount' => 500]],
                'new_items' => [['concept_id' => $monthlyConcept->id, 'total' => 500]],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('daños materiales', $response->json('message'));
    }

    public function test_cobranza_sin_cd_cobra_otros_conceptos_como_antes(): void
    {
        // Cargo normal (no es CD ni mensualidad) en una cuenta sin CD.
        $otherCharge = $this->createCharge($this->accountPark1, 'PHYSICAL_AD', 350);

        $response = $this->asCashierOfClub($this->accountPark1->club_id)
            ->postJson(route('collections.payment.store'), [
                'membership_account_id' => $this->accountPark1->id,
                'club_id' => $this->accountPark1->club_id,
                'paid_at' => now()->toDateString(),
                'payments' => [['payment_method_id' => $this->cashMethodId(), 'amount' => 350]],
                'existing_charges' => [['charge_id' => $otherCharge->id, 'amount' => 350]],
            ]);

        $response->assertOk();
        $this->assertSame('paid', $otherCharge->fresh()->status);
    }

    public function test_cobranza_permite_cobrar_el_cd_solo(): void
    {
        $damageCharge = $this->createCharge($this->accountPark1, 'CD', 800);

        $this->payDamageChargeFromCollections($damageCharge);

        $this->assertSame('paid', $damageCharge->fresh()->status);
        $this->assertEquals(0, (float) $damageCharge->fresh()->balance);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Ayudantes
    // ─────────────────────────────────────────────────────────────────────

    /** Verifica que la función lance el error de "primero liquida el CD". */
    private function assertPaymentIsBlocked(callable $action): void
    {
        try {
            $action();
            $this->fail('Se esperaba que el pago se bloqueara por el cargo de daños materiales.');
        } catch (ValidationException $e) {
            $this->assertSame(
                MaterialDamagePaymentGuard::BLOCKED_PAYMENT_MESSAGE,
                collect($e->errors())->flatten()->first()
            );
        }
    }

    /** Busca un usuario con cuenta activa en dos parques distintos. */
    private function findUserWithAccountsInBothParks(): array
    {
        $groupIds = MembershipAccount::query()
            ->whereNotNull('account_group_id')
            ->where('status', '!=', 'cancelled')
            ->pluck('account_group_id')
            ->unique();

        foreach ($groupIds as $groupId) {
            $accounts = MembershipAccount::query()
                ->with('primaryHolder')
                ->where('account_group_id', $groupId)
                ->where('status', '!=', 'cancelled')
                ->whereHas('primaryHolder')
                ->orderBy('club_id')
                ->get();

            $hasActiveMemberships = $accounts->every(fn ($account) => Membership::query()
                ->where('membership_account_id', $account->id)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->exists());

            if ($accounts->count() === 2 && $accounts[0]->club_id !== $accounts[1]->club_id && $hasActiveMemberships) {
                return [$accounts[0], $accounts[1]];
            }
        }

        $this->markTestSkipped('No hay un usuario con cuenta activa en ambos parques en la base local.');
    }

    /** Crea un cargo para la cuenta indicada. */
    private function createCharge(MembershipAccount $account, string $conceptCode, float $amount, string $status = 'pending'): Charge
    {
        $concept = ChargeConcept::query()->where('code', $conceptCode)->firstOrFail();

        $membership = Membership::query()
            ->where('membership_account_id', $account->id)
            ->where('is_primary', true)
            ->first();

        return Charge::create([
            'membership_account_id' => $account->id,
            'membership_id' => $membership?->id,
            'member_id' => $account->primaryHolder?->member_id,
            'concept_id' => $concept->id,
            'description' => 'Prueba automática ' . $conceptCode,
            'amount' => $amount,
            'balance' => $status === 'paid' ? 0 : $amount,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'allows_partial_payments' => false,
            'status' => $status,
        ]);
    }

    /** Paga el CD desde Cobranza (solo el CD, completo) y regresa el pago. */
    private function payDamageChargeFromCollections(Charge $damageCharge): Payment
    {
        $account = MembershipAccount::query()->findOrFail($damageCharge->membership_account_id);
        $amount = (float) $damageCharge->balance;

        $response = $this->asCashierOfClub($account->club_id)
            ->postJson(route('collections.payment.store'), [
                'membership_account_id' => $account->id,
                'club_id' => $account->club_id,
                'paid_at' => now()->toDateString(),
                'payments' => [['payment_method_id' => $this->cashMethodId(), 'amount' => $amount]],
                'existing_charges' => [['charge_id' => $damageCharge->id, 'amount' => $amount]],
            ]);

        $response->assertOk();

        return Payment::query()->findOrFail($response->json('payment_ids.0'));
    }

    /**
     * Simula a un cajero con sesión en el parque indicado. Se quitan los
     * middlewares (login, permisos) porque aquí se prueba la regla del CD,
     * no los permisos.
     */
    private function asCashierOfClub(int $clubId): static
    {
        $user = User::query()->first();

        return $this->withoutMiddleware()
            ->actingAs($user)
            ->withSession(['club_id' => $clubId]);
    }

    private function cashMethodId(): int
    {
        return (int) DB::table('billing.payment_methods')->where('code', 'CASH')->value('id');
    }
}
