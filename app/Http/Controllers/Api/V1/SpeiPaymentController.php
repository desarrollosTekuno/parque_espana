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
     */
    public function store(Request $request, int $club): JsonResponse
    {
        $validated = $request->validate([
            'applications'             => ['required', 'array', 'min:1'],
            'applications.*.charge_id' => ['required', 'integer'],
            'applications.*.amount'    => ['required', 'numeric', 'gt:0'],
            'notes'                    => ['nullable', 'string', 'max:500'],
        ]);

        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');
        }

        $paymentMethod = $this->resolveSpeiPaymentMethod($club);

        if (!$paymentMethod) {
            return $this->unprocessable('El pago por transferencia SPEI no está habilitado para este club. Contacta a administración.');
        }

        $account = $member->accounts()
            ->whereHas('memberships', fn ($q) => $q
                ->where('club_id', $club)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$account) {
            return $this->notFound('No se encontró una membresía activa en este club.');
        }

        $chargeIds = collect($validated['applications'])->pluck('charge_id');
        $charges   = Charge::whereIn('id', $chargeIds)
            ->where('membership_account_id', $account->id)
            ->whereIn('status', ['pending', 'partial'])
            ->with('concept')
            ->get();

        if ($charges->count() !== $chargeIds->count()) {
            return $this->unprocessable('Uno o más cargos no son válidos o ya fueron pagados.');
        }

        $totalAmount = collect($validated['applications'])->sum('amount');
        $amountCents = (int) round($totalAmount * 100);
        $description = $this->buildDescription($account, $charges);
        $expiresAt   = now()->addHours(config('conekta.spei_expiration_hours', 72))->timestamp;

        try {
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

            return $this->created('Orden SPEI generada. Realiza la transferencia antes de la fecha de vencimiento.', [
                'spei_order_id' => $speiOrder->id,
                'clabe'         => $speiResult['clabe'],
                'bank'          => $speiResult['bank'],
                'amount'        => $totalAmount,
                'expires_at'    => $speiOrder->expires_at->toIso8601String(),
                'description'   => $description,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return $this->serverError('No se pudo generar la orden SPEI. Intenta de nuevo.');
        }
    }

    /**
     * GET /api/v1/clubs/{club}/spei-payment/{speiOrder}
     */
    public function show(Request $request, int $club, SpeiOrder $speiOrder): JsonResponse
    {
        $member  = Member::where('user_id', $request->user()->id)->first();
        $account = $member?->accounts()
            ->where('memberships.accounts.id', $speiOrder->membership_account_id)
            ->first();

        if (!$member || !$account || $speiOrder->club_id !== $club) {
            return $this->notFound('Orden no encontrada.');
        }

        if ($speiOrder->isPending() && $speiOrder->isExpired()) {
            $speiOrder->update(['status' => 'expired']);
        }

        return $this->ok([
            'spei_order_id' => $speiOrder->id,
            'clabe'         => $speiOrder->clabe,
            'bank'          => $speiOrder->bank,
            'amount'        => $speiOrder->amount,
            'expires_at'    => $speiOrder->expires_at->toIso8601String(),
            'status'        => $speiOrder->status,
            'payment_id'    => $speiOrder->payment_id,
        ]);
    }

    private function resolveSpeiPaymentMethod(int $clubId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('code', config('conekta.spei_payment_method_code', 'SPEI'))
            ->where('is_active', true)
            ->whereHas('clubPaymentMethods', fn ($q) =>
                $q->where('club_id', $clubId)->where('is_active', true)
            )
            ->first();
    }

    private function buildDescription(MembershipAccount $account, $charges): string
    {
        $concepts = $charges->map(fn ($c) => $c->concept?->name ?? "Cargo #{$c->id}")->join(', ');
        return "Cuenta #{$account->membership_number}: {$concepts}";
    }
}
