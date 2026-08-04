<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing\Charge;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use App\Models\Memberships\MembershipAccount;
use App\Services\Billing\InterclubSplitPaymentService;
use App\Services\Billing\PaymentRegistrationService;
use App\Services\Payments\ConektaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChargePaymentController extends Controller
{
    public function __construct(
        private ConektaService              $conekta,
        private PaymentRegistrationService  $paymentService,
        private InterclubSplitPaymentService $splitService,
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

        $splitContext = $this->splitService->resolveContext($member, $clubId, $charges);

        if ($splitContext) {
            return $this->processSplitPayment(
                member: $member,
                account: $account,
                clubId: $clubId,
                source: $source,
                paymentMethod: $paymentMethod,
                charges: $charges,
                applications: $validated['applications'],
                notes: $validated['notes'] ?? null,
                splitContext: $splitContext,
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

    /**
     * Cobra un pago que incluye al menos un cargo de un concepto con
     * splits_between_parks=true, para un socio titular en ambos parques.
     * Se parte cada cargo elegible a la mitad (ver InterclubSplitPaymentService)
     * y se hacen DOS cobros Conekta independientes, uno por cuenta comercial —
     * cada parque termina con su propio cargo pagado y su propio registro en
     * el corte de caja. El socio nunca ve estos dos pasos, solo mandó un
     * POST /charge-payment normal.
     */
    private function processSplitPayment(
        Member $member,
        MembershipAccount $account,
        int $clubId,
        MemberPaymentSource $source,
        PaymentMethod $paymentMethod,
        Collection $charges,
        array $applications,
        ?string $notes,
        array $splitContext,
    ): JsonResponse {
        $otherClubId   = $splitContext['club_id'];
        $otherAccount  = $splitContext['account'];
        $otherMembership = $splitContext['membership'];

        $otherSource = $this->splitService->resolvePaymentSource($member->id, $otherClubId);

        if (!$otherSource) {
            return $this->unprocessable(
                "Para completar este pago necesitas una tarjeta guardada también en {$splitContext['club_name']}. Agrégala e intenta de nuevo."
            );
        }

        $otherPaymentMethod = $this->splitService->resolveConektaPaymentMethod($otherClubId);

        if (!$otherPaymentMethod) {
            return $this->unprocessable(
                "El pago con tarjeta no está habilitado en {$splitContext['club_name']}. Contacta a administración."
            );
        }

        $applicationsByChargeId = collect($applications)->keyBy('charge_id');
        $originalApplications  = [];
        $mirrorApplications    = [];

        foreach ($charges as $charge) {
            $requestedAmount = round((float) $applicationsByChargeId[$charge->id]['amount'], 2);

            if ($charge->concept?->splits_between_parks) {
                $split = $this->splitService->splitCharge($charge, $otherAccount, $otherMembership);
                $originalApplications[] = ['charge_id' => $charge->id, 'amount' => $split['original_share']];
                $mirrorApplications[]   = ['charge_id' => $split['mirror_charge']->id, 'amount' => (float) $split['mirror_charge']->balance];
            } else {
                $originalApplications[] = ['charge_id' => $charge->id, 'amount' => $requestedAmount];
            }
        }

        $originalTotal = round(collect($originalApplications)->sum('amount'), 2);
        $otherTotal    = round(collect($mirrorApplications)->sum('amount'), 2);
        $description   = $this->buildDescription($account, $charges);

        try {
            $firstResult = $this->conekta->charge(
                member:      $member,
                source:      $source,
                clubId:      $clubId,
                amountCents: (int) round($originalTotal * 100),
                description: $description,
                metadata: [
                    'membership_account_id' => $account->id,
                    'club_id'               => $clubId,
                    'charge_ids'            => collect($originalApplications)->pluck('charge_id')->join(','),
                    'interclub_split'       => true,
                ],
            );

            if ($firstResult['status'] !== 'paid') {
                return response()->json([
                    'message' => 'El pago fue rechazado por el procesador. Verifica los datos de tu tarjeta.',
                    'data'    => ['conekta_status' => $firstResult['status']],
                ], 422);
            }

            $this->paymentService->register(
                account:       $account,
                clubId:        $clubId,
                paymentMethod: $paymentMethod,
                applications:  $originalApplications,
                paidAt:        now()->toDateString(),
                reference:     $firstResult['order_id'],
                bankName:      null,
                checkNumber:   null,
                notes:         $notes ?? "Pago procesado vía Conekta (mitad interclub). Cargo: {$firstResult['charge_id']}",
                receivedBy:    null,
                sessionClubId: $clubId,
            );
        } catch (\Throwable $e) {
            report($e);
            return $this->serverError('Ocurrió un error al procesar el pago. Intenta de nuevo.');
        }

        // A partir de aquí ya se cobró y registró la mitad de este parque —
        // si algo falla abajo, no se puede deshacer ese cobro, así que se
        // responde con éxito parcial en vez de un error genérico.
        try {
            $secondResult = $this->conekta->charge(
                member:      $member,
                source:      $otherSource,
                clubId:      $otherClubId,
                amountCents: (int) round($otherTotal * 100),
                description: $description,
                metadata: [
                    'membership_account_id' => $otherAccount->id,
                    'club_id'               => $otherClubId,
                    'charge_ids'            => collect($mirrorApplications)->pluck('charge_id')->join(','),
                    'interclub_split'       => true,
                ],
            );

            if ($secondResult['status'] !== 'paid') {
                return response()->json([
                    'message' => "Se cobró tu parte en este parque, pero el cobro en {$splitContext['club_name']} fue rechazado. Contacta a administración para completar tu pago.",
                    'data'    => ['conekta_status' => $secondResult['status'], 'partial' => true],
                ], 422);
            }

            $this->paymentService->register(
                account:       $otherAccount,
                clubId:        $otherClubId,
                paymentMethod: $otherPaymentMethod,
                applications:  $mirrorApplications,
                paidAt:        now()->toDateString(),
                reference:     $secondResult['order_id'],
                bankName:      null,
                checkNumber:   null,
                notes:         "Pago procesado vía Conekta (mitad interclub). Cargo: {$secondResult['charge_id']}",
                receivedBy:    null,
                sessionClubId: $otherClubId,
            );
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => "Se cobró tu parte en este parque, pero ocurrió un error al procesar el cobro en {$splitContext['club_name']}. Contacta a administración para completar tu pago.",
            ], 422);
        }

        return $this->created('Pago procesado correctamente.', [
            'amount'  => round($originalTotal + $otherTotal, 2),
            'paid_at' => now()->toDateString(),
            'split'   => true,
        ]);
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

    private function buildDescription(MembershipAccount $account, Collection $charges): string
    {
        $conceptNames = $charges
            ->map(fn (Charge $c) => $c->concept?->name ?? "Cargo #{$c->id}")
            ->join(', ');

        return "Cuenta #{$account->membership_number}: {$conceptNames}";
    }
}
