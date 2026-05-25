<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing\Charge;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use App\Models\Memberships\MembershipAccount;
use App\Services\Billing\PaymentRegistrationService;
use App\Services\Payments\ConektaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChargePaymentController extends Controller
{
    public function __construct(
        private ConektaService             $conekta,
        private PaymentRegistrationService $paymentService,
    ) {}

    /**
     * POST /api/v1/charge-payment
     *
     * Cobra uno o más cargos pendientes usando una tarjeta domiciliada.
     * El cobro se procesa en Conekta y queda registrado en billing.
     *
     * Body:
     * {
     *   "payment_source_id": 1,          // ID local de la tarjeta (members.payment_sources)
     *   "club_id": 1,                    // Club al que pertenecen los cargos
     *   "applications": [
     *     { "charge_id": 123, "amount": 500.00 },
     *     { "charge_id": 124, "amount": 250.00 }
     *   ],
     *   "notes": "Pago desde app"        // opcional
     * }
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Resolver miembro autenticado
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil de socio asociado a este usuario.',
            ], 404);
        }

        // 2. Validar request
        $validated = $request->validate([
            'payment_source_id'           => ['required', 'integer'],
            'club_id'                     => ['required', 'integer'],
            'applications'                => ['required', 'array', 'min:1'],
            'applications.*.charge_id'    => ['required', 'integer'],
            'applications.*.amount'       => ['required', 'numeric', 'gt:0'],
            'notes'                       => ['nullable', 'string', 'max:500'],
        ]);

        // 3. Validar que la tarjeta pertenezca al miembro
        $source = MemberPaymentSource::where('id', $validated['payment_source_id'])
            ->where('member_id', $member->id)
            ->first();

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'La tarjeta seleccionada no está disponible.',
            ], 404);
        }

        // 4. Encontrar el método de pago Conekta habilitado para el club
        $clubId        = (int) $validated['club_id'];
        $paymentMethod = $this->resolveConektaPaymentMethod($clubId);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'El pago con tarjeta no está habilitado para este club. Contacta a administración.',
            ], 422);
        }

        // 5. Resolver la cuenta del miembro en el club
        $account = $member->accounts()
            ->whereHas('memberships', fn ($q) => $q->where('club_id', $clubId)->where('status', 'active'))
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una membresía activa en este club.',
            ], 404);
        }

        // 6. Calcular total en centavos para Conekta
        $totalAmount = collect($validated['applications'])->sum('amount');
        $amountCents = (int) round($totalAmount * 100);

        // 7. Construir descripción del cargo
        $chargeIds   = collect($validated['applications'])->pluck('charge_id');
        $description = $this->buildDescription($account, $chargeIds->toArray());

        try {
            // 8. Procesar el cobro en Conekta
            $conektaResult = $this->conekta->charge(
                member:      $member,
                source:      $source,
                amountCents: $amountCents,
                description: $description,
                metadata: [
                    'membership_account_id' => $account->id,
                    'club_id'               => $clubId,
                    'charge_ids'            => $chargeIds->join(','),
                ],
            );

            if ($conektaResult['status'] !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'El pago fue rechazado por el procesador. Verifica los datos de tu tarjeta.',
                    'conekta_status' => $conektaResult['status'],
                ], 422);
            }

            // 9. Registrar el pago en billing
            $payment = $this->paymentService->register(
                account:       $account,
                clubId:        $clubId,
                paymentMethod: $paymentMethod,
                applications:  $validated['applications'],
                paidAt:        now()->toDateString(),
                reference:     $conektaResult['order_id'],
                bankName:      null,
                checkNumber:   null,
                notes:         $validated['notes'] ?? "Pago procesado vía Conekta. Cargo: {$conektaResult['charge_id']}",
                receivedBy:    null,
                sessionClubId: $clubId,
            );

            return response()->json([
                'success'    => true,
                'message'    => 'Pago procesado correctamente.',
                'data'       => [
                    'payment_id'      => $payment->id,
                    'amount'          => $payment->amount,
                    'paid_at'         => $payment->paid_at,
                    'conekta_order'   => $conektaResult['order_id'],
                    'conekta_charge'  => $conektaResult['charge_id'],
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar los cargos.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar el pago. Intenta de nuevo.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Busca el PaymentMethod con provider='conekta' habilitado para el club.
     */
    private function resolveConektaPaymentMethod(int $clubId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('provider', PaymentMethod::PROVIDER_CONEKTA)
            ->where('is_active', true)
            ->whereHas('clubPaymentMethods', fn ($q) =>
                $q->where('club_id', $clubId)->where('is_active', true)
            )
            ->first();
    }

    /**
     * Genera una descripción legible para el cargo en Conekta.
     */
    private function buildDescription(MembershipAccount $account, array $chargeIds): string
    {
        $charges = Charge::whereIn('id', $chargeIds)
            ->with('concept')
            ->get()
            ->map(fn ($c) => $c->concept?->name ?? "Cargo #{$c->id}")
            ->join(', ');

        return "Cuenta #{$account->membership_number}: {$charges}";
    }
}
