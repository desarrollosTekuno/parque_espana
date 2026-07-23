<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing\Charge;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SpeiOrder;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccount;
use App\Services\Payments\ConektaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeiPaymentController extends Controller
{
    public function __construct(private ConektaService $conekta) {}

    /**
     * POST /api/v1/clubs/{club}/spei-payment
     *
     * Genera una orden SPEI en Conekta y devuelve el CLABE para que el socio
     * haga la transferencia desde su banco.
     *
     * El pago se confirma de forma asíncrona vía webhook (POST /webhooks/conekta).
     * El socio puede tener múltiples órdenes SPEI pendientes simultáneamente.
     *
     * Body:
     * {
     *   "applications": [
     *     { "charge_id": 123, "amount": 2500.00 },
     *     { "charge_id": 124, "amount": 1100.00 }
     *   ],
     *   "notes": "Transferencia desde BBVA"   // opcional
     * }
     */
    public function store(Request $request, int $club): JsonResponse
    {
        $validated = $request->validate([
            'applications'             => ['required', 'array', 'min:1'],
            'applications.*.charge_id' => ['required', 'integer'],
            'applications.*.amount'    => ['required', 'numeric', 'gt:0'],
            'notes'                    => ['nullable', 'string', 'max:500'],
        ]);

        // ── 1. Socio autenticado ──────────────────────────────────────────
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil de socio asociado a este usuario.',
            ], 404);
        }

        // ── 2. Método de pago SPEI habilitado para el club ────────────────
        $paymentMethod = $this->resolveSpeiPaymentMethod($club);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'El pago por transferencia SPEI no está habilitado para este club. Contacta a administración.',
            ], 422);
        }

        // ── 3. Cuenta activa del miembro en el club ───────────────────────
        $account = $member->accounts()
            ->whereHas('memberships', fn($q) => $q
                ->where('club_id', $club)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una membresía activa en este club.',
            ], 404);
        }

        // ── 4. Validar que los cargos pertenezcan a la cuenta ────────────
        $chargeIds = collect($validated['applications'])->pluck('charge_id');
        $charges   = Charge::whereIn('id', $chargeIds)
            ->where('membership_account_id', $account->id)
            ->whereIn('status', ['pending', 'partial'])
            ->with('concept')
            ->get();

        if ($charges->count() !== $chargeIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Uno o más cargos no son válidos o ya fueron pagados.',
            ], 422);
        }

        // ── 5. Calcular total y construir descripción ─────────────────────
        $totalAmount = collect($validated['applications'])->sum('amount');
        $amountCents = (int) round($totalAmount * 100);
        $description = $this->buildDescription($account, $charges);
        $expiresAt   = now()->addHours(config('conekta.spei_expiration_hours', 72))->timestamp;

        try {
            // ── 6. Crear orden SPEI en Conekta ────────────────────────────
            $speiResult = $this->conekta->createSpeiOrder(
                member:      $member,
                clubId:      $club,
                amountCents: $amountCents,
                description: $description,
                expiresAt:   $expiresAt,
                metadata: [
                    'membership_account_id' => $account->id,
                    'club_id'               => $club,
                    'charge_ids'            => $chargeIds->join(','),
                ],
            );

            // ── 7. Guardar la orden SPEI pendiente ────────────────────────
            $speiOrder = SpeiOrder::create([
                'membership_account_id' => $account->id,
                'club_id'               => $club,
                'payment_method_id'     => $paymentMethod->id,
                'conekta_order_id'      => $speiResult['order_id'],
                'conekta_charge_id'     => $speiResult['charge_id'],
                'clabe'                 => $speiResult['clabe'],
                'bank'                  => $speiResult['bank'],
                'amount'                => $totalAmount,
                'expires_at'            => now()->setTimestamp($expiresAt),
                'status'                => 'pending',
                'applications'          => $validated['applications'],
                'notes'                 => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden SPEI generada. Realiza la transferencia antes de la fecha de vencimiento.',
                'data'    => [
                    'spei_order_id' => $speiOrder->id,
                    'clabe'         => $speiResult['clabe'],
                    'bank'          => $speiResult['bank'],
                    'amount'        => $totalAmount,
                    'expires_at'    => $speiOrder->expires_at->toIso8601String(),
                    'description'   => $description,
                ],
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar la orden SPEI. Intenta de nuevo.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/clubs/{club}/spei-payment/{speiOrder}
     *
     * Consulta el estado de una orden SPEI. La app puede hacer polling
     * mientras espera la confirmación del pago.
     */
    public function show(Request $request, int $club, SpeiOrder $speiOrder): JsonResponse
    {
        // Verificar que la orden pertenezca a este miembro y club
        $member = Member::where('user_id', $request->user()->id)->first();

        $account = $member?->accounts()
            ->where('memberships.accounts.id', $speiOrder->membership_account_id)
            ->first();

        if (!$member || !$account || $speiOrder->club_id !== $club) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.',
            ], 404);
        }

        // Marcar como expirada si ya venció y sigue pending
        if ($speiOrder->isPending() && $speiOrder->isExpired()) {
            $speiOrder->update(['status' => 'expired']);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'spei_order_id' => $speiOrder->id,
                'clabe'         => $speiOrder->clabe,
                'bank'          => $speiOrder->bank,
                'amount'        => $speiOrder->amount,
                'expires_at'    => $speiOrder->expires_at->toIso8601String(),
                'status'        => $speiOrder->status,
                'payment_id'    => $speiOrder->payment_id,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveSpeiPaymentMethod(int $clubId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('code', config('conekta.spei_payment_method_code', 'SPEI'))
            ->where('is_active', true)
            ->whereHas('clubPaymentMethods', fn($q) =>
                $q->where('club_id', $clubId)->where('is_active', true)
            )
            ->first();
    }

    private function buildDescription(MembershipAccount $account, $charges): string
    {
        $concepts = $charges->map(fn($c) => $c->concept?->name ?? "Cargo #{$c->id}")->join(', ');
        return "Cuenta #{$account->membership_number}: {$concepts}";
    }
}
