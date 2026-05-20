<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing\SpeiOrder;
use App\Services\Billing\PaymentRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConektaWebhookController extends Controller
{
    public function __construct(private PaymentRegistrationService $paymentService) {}

    /**
     * POST /api/v1/webhooks/conekta
     *
     * Receptor de eventos de Conekta. Sin middleware auth:sanctum.
     *
     * Seguridad:
     *  - Configura en el panel de Conekta que solo envíe desde 52.200.151.182
     *  - Cualquier order_id que no exista en nuestra BD es ignorado silenciosamente
     *
     * Idempotencia:
     *  - Si la orden ya está pagada (status = paid) se retorna 200 sin reprocesar
     *
     * Eventos manejados:
     *  - charge.paid → confirma el pago SPEI y registra en billing
     *
     * Conekta reintenta el webhook hasta 13 veces si no recibe HTTP 200.
     * Por eso siempre se retorna 200, incluso cuando el evento se ignora.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $type    = $payload['type'] ?? null;

        Log::info('Conekta webhook recibido', ['type' => $type]);

        try {
            match ($type) {
                'charge.paid' => $this->handleChargePaid($payload),
                default       => null, // Ignorar otros eventos
            };
        } catch (\Throwable $e) {
            // Loguear pero retornar 200 para evitar reintentos innecesarios
            // de eventos que nunca podremos procesar
            Log::error('Error procesando webhook de Conekta', [
                'type'    => $type,
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        // Siempre 200 — Conekta reintenta si recibe cualquier otro código
        return response('OK', 200);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function handleChargePaid(array $payload): void
    {
        $chargeData = $payload['data']['object'] ?? null;

        if (!$chargeData) {
            Log::warning('Webhook charge.paid sin data.object', ['payload' => $payload]);
            return;
        }

        $conektaOrderId  = $chargeData['order_id'] ?? null;
        $conektaChargeId = $chargeData['id']       ?? null;
        $status          = $chargeData['status']   ?? null;

        if (!$conektaOrderId || $status !== 'paid') {
            return;
        }

        // Buscar la orden SPEI pendiente en nuestra BD
        $speiOrder = SpeiOrder::where('conekta_order_id', $conektaOrderId)
            ->with(['membershipAccount', 'paymentMethod'])
            ->first();

        if (!$speiOrder) {
            // No es una orden SPEI nuestra — ignorar
            Log::info('Webhook charge.paid para orden desconocida', [
                'conekta_order_id' => $conektaOrderId,
            ]);
            return;
        }

        // Idempotencia: ya fue procesado
        if (!$speiOrder->isPending()) {
            Log::info('Webhook charge.paid duplicado ignorado', [
                'spei_order_id' => $speiOrder->id,
                'status'        => $speiOrder->status,
            ]);
            return;
        }

        // Registrar el pago y marcar la orden en una sola transacción
        DB::transaction(function () use ($speiOrder, $conektaOrderId, $conektaChargeId) {
            $paidAt = now()->toDateString();

            $payment = $this->paymentService->register(
                account:       $speiOrder->membershipAccount,
                clubId:        $speiOrder->club_id,
                paymentMethod: $speiOrder->paymentMethod,
                applications:  $speiOrder->applications,
                paidAt:        $paidAt,
                reference:     $conektaOrderId,
                bankName:      $speiOrder->bank,
                checkNumber:   null,
                notes:         "Pago SPEI confirmado por Conekta. Cargo: {$conektaChargeId}. " . ($speiOrder->notes ?? ''),
                receivedBy:    null,
                sessionClubId: $speiOrder->club_id,
            );

            $speiOrder->update([
                'status'     => 'paid',
                'payment_id' => $payment->id,
            ]);

            Log::info('Pago SPEI confirmado vía webhook', [
                'spei_order_id'    => $speiOrder->id,
                'payment_id'       => $payment->id,
                'conekta_order_id' => $conektaOrderId,
                'amount'           => $speiOrder->amount,
            ]);
        });
    }
}
