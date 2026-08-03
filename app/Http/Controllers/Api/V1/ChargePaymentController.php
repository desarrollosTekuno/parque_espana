<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing\Charge;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use App\Models\Memberships\MembershipAccount;
use App\Services\Billing\PaymentRegistrationService;
use App\Services\Payments\ConektaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargePaymentController extends Controller
{
    public function __construct(
        private ConektaService             $conekta,
        private PaymentRegistrationService $paymentService,
    ) {}

    /**
     * POST /api/v1/charge-payment
     */
    public function store(Request $request): JsonResponse
    {
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');
        }

        $validated = $request->validate([
            'payment_source_id'           => ['required', 'integer'],
            'club_id'                     => ['required', 'integer'],
            'applications'                => ['required', 'array', 'min:1'],
            'applications.*.charge_id'    => ['required', 'integer'],
            'applications.*.amount'       => ['required', 'numeric', 'gt:0'],
            'notes'                       => ['nullable', 'string', 'max:500'],
        ]);

        $clubId = (int) $validated['club_id'];
        $source = MemberPaymentSource::where('id', $validated['payment_source_id'])
            ->where('member_id', $member->id)
            ->where('club_id', $clubId)
            ->first();

        if (!$source) {
            return $this->notFound('La tarjeta seleccionada no está disponible.');
        }

        $paymentMethod = $this->resolveConektaPaymentMethod($clubId);

        if (!$paymentMethod) {
            return $this->unprocessable('El pago con tarjeta no está habilitado para este club. Contacta a administración.');
        }

        $account = $member->accounts()
            ->whereHas('memberships', fn ($q) => $q->where('club_id', $clubId)->where('status', 'active'))
            ->first();

        if (!$account) {
            return $this->notFound('No se encontró una membresía activa en este club.');
        }

        $totalAmount = collect($validated['applications'])->sum('amount');
        $amountCents = (int) round($totalAmount * 100);
        $chargeIds   = collect($validated['applications'])->pluck('charge_id');
        $charges     = Charge::whereIn('id', $chargeIds)->with('concept')->get();

        $nonPayableCharge = $charges->first(fn (Charge $c) => $c->concept && !$c->concept->is_mobile_payable);

        if ($nonPayableCharge) {
            return $this->unprocessable(
                "El concepto \"{$nonPayableCharge->concept->name}\" no está disponible para pago desde la app. Acude a la administración del club."
            );
        }

        $description = $this->buildDescription($account, $charges);

        try {
            $conektaResult = $this->conekta->charge(
                member:      $member,
                source:      $source,
                clubId:      $clubId,
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
                    'message' => 'El pago fue rechazado por el procesador. Verifica los datos de tu tarjeta.',
                    'data'    => ['conekta_status' => $conektaResult['status']],
                ], 422);
            }

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

            return $this->created('Pago procesado correctamente.', [
                'payment_id'     => $payment->id,
                'amount'         => $payment->amount,
                'paid_at'        => $payment->paid_at,
                'conekta_order'  => $conektaResult['order_id'],
                'conekta_charge' => $conektaResult['charge_id'],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return $this->serverError('Ocurrió un error al procesar el pago. Intenta de nuevo.');
        }
    }

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

    private function buildDescription(MembershipAccount $account, \Illuminate\Support\Collection $charges): string
    {
        $conceptNames = $charges
            ->map(fn (Charge $c) => $c->concept?->name ?? "Cargo #{$c->id}")
            ->join(', ');

        return "Cuenta #{$account->membership_number}: {$conceptNames}";
    }
}
