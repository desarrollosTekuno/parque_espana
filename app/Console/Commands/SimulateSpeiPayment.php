<?php

namespace App\Console\Commands;

use App\Models\Billing\SpeiOrder;
use App\Services\Billing\PaymentRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulateSpeiPayment extends Command
{
    protected $signature = 'spei:simulate
        {order : ID local de la SpeiOrder (billing.spei_orders.id)}
        {--dry-run : Muestra lo que se registraría sin hacer cambios en BD}';

    protected $description = 'Simula la llegada del webhook charge.paid de Conekta para una orden SPEI pendiente. Útil en desarrollo local.';

    public function __construct(private PaymentRegistrationService $paymentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $orderId = $this->argument('order');
        $dryRun  = $this->option('dry-run');

        // ── Buscar la orden ───────────────────────────────────────────────
        $speiOrder = SpeiOrder::find($orderId);

        if (!$speiOrder) {
            $this->error("No se encontró una SpeiOrder con ID {$orderId}.");
            return self::FAILURE;
        }

        $this->info("SpeiOrder encontrada:");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID',               $speiOrder->id],
                ['Conekta Order ID', $speiOrder->conekta_order_id],
                ['CLABE',            $speiOrder->clabe],
                ['Banco',            $speiOrder->bank ?? '—'],
                ['Monto',            '$' . number_format($speiOrder->amount, 2) . ' MXN'],
                ['Vence',            $speiOrder->expires_at->format('d/m/Y H:i')],
                ['Status',           $speiOrder->status],
            ]
        );

        // ── Validaciones ──────────────────────────────────────────────────
        if (!$speiOrder->isPending()) {
            $this->warn("La orden ya tiene status '{$speiOrder->status}'. No se puede reprocesar.");
            return self::FAILURE;
        }

        if ($speiOrder->isExpired()) {
            $this->warn('La orden ya venció. En producción Conekta no enviaría el webhook.');
            if (!$this->confirm('¿Continuar de todas formas para propósitos de prueba?')) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->line("Cargos que se marcarán como pagados:");
        foreach ($speiOrder->applications as $app) {
            $this->line("  • Cargo #{$app['charge_id']} — $" . number_format($app['amount'], 2) . ' MXN');
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('[DRY-RUN] No se realizaron cambios en la base de datos.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Simular el webhook charge.paid y registrar el pago?')) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        // ── Ejecutar la misma lógica del webhook ──────────────────────────
        try {
            DB::transaction(function () use ($speiOrder) {
                $payment = $this->paymentService->register(
                    account:       $speiOrder->load('membershipAccount')->membershipAccount,
                    clubId:        $speiOrder->club_id,
                    paymentMethod: $speiOrder->load('paymentMethod')->paymentMethod,
                    applications:  $speiOrder->applications,
                    paidAt:        now()->toDateString(),
                    reference:     $speiOrder->conekta_order_id,
                    bankName:      $speiOrder->bank,
                    checkNumber:   null,
                    notes:         '[SIMULADO] Pago SPEI confirmado localmente. Cargo: ' . ($speiOrder->conekta_charge_id ?? 'N/A'),
                    receivedBy:    null,
                    sessionClubId: $speiOrder->club_id,
                );

                $speiOrder->update([
                    'status'     => 'paid',
                    'payment_id' => $payment->id,
                ]);

                $this->newLine();
                $this->info('✓ Pago registrado correctamente.');
                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['Payment ID',  $payment->id],
                        ['Monto',       '$' . number_format($payment->amount, 2) . ' MXN'],
                        ['Fecha pago',  $payment->paid_at],
                        ['Referencia',  $payment->reference],
                        ['SpeiOrder',   "status → paid"],
                    ]
                );

                Log::info('[SIMULADO] SpeiPayment webhook procesado', [
                    'spei_order_id' => $speiOrder->id,
                    'payment_id'    => $payment->id,
                ]);
            });
        } catch (\Throwable $e) {
            $this->error('Error al procesar el pago: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
