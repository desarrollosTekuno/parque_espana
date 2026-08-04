<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Support\Collection;

/**
 * Cuando un concepto tiene billing.concepts.splits_between_parks = true y el
 * socio que paga es titular activo en ambos parques, el cobro se reparte
 * 50/50 entre las dos cuentas Conekta (cada parque es un comercio distinto).
 * No hay forma de aplicar un pago de un club a un cargo de otro club (ver
 * PaymentRegistrationService::ensureChargesBelongToClub) — por eso aquí se
 * "parte" el cargo original a la mitad y se crea un cargo espejo real en la
 * cuenta del socio en el otro parque, para que cada lado quede con su propio
 * cargo pagado y su corte de caja lo refleje correctamente.
 */
class InterclubSplitPaymentService
{
    /**
     * Si algún cargo del pago requiere split y el socio es titular activo en
     * otro club, regresa el contexto de ese otro club. Si no aplica ninguna
     * de las dos condiciones, regresa null (el pago sigue su flujo normal).
     *
     * @return array{club_id: int, club_name: string, account: MembershipAccount, membership: Membership}|null
     */
    public function resolveContext(Member $member, int $clubId, Collection $charges): ?array
    {
        $needsSplit = $charges->contains(
            fn (Charge $charge) => (bool) ($charge->concept?->splits_between_parks ?? false)
        );

        if (!$needsSplit) {
            return null;
        }

        $otherMembership = Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', '!=', $clubId)
            ->whereHas('account.accountMembers', fn ($q) => $q
                ->where('member_id', $member->id)
                ->where('is_primary_holder', true))
            ->with(['club', 'account'])
            ->first();

        if (!$otherMembership || !$otherMembership->account) {
            return null;
        }

        return [
            'club_id' => (int) $otherMembership->club_id,
            'club_name' => $otherMembership->club?->name ?? 'el otro parque',
            'account' => $otherMembership->account,
            'membership' => $otherMembership,
        ];
    }

    public function resolvePaymentSource(int $memberId, int $clubId): ?MemberPaymentSource
    {
        return MemberPaymentSource::query()
            ->where('member_id', $memberId)
            ->where('club_id', $clubId)
            ->orderByDesc('is_default')
            ->first();
    }

    public function resolveConektaPaymentMethod(int $clubId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('provider', PaymentMethod::PROVIDER_CONEKTA)
            ->where('is_active', true)
            ->whereHas('clubPaymentMethods', fn ($q) => $q
                ->where('club_id', $clubId)
                ->where('is_active', true))
            ->first();
    }

    /**
     * Parte un cargo a la mitad: el original se queda (mutado) con la mitad
     * del monto en el club de origen; la otra mitad se cobra en un cargo
     * nuevo en la cuenta del socio en el otro parque. Es idempotente — si
     * el cargo ya se había partido antes (ej. un reintento tras un pago
     * fallido), reutiliza el mismo cargo espejo en vez de crear otro.
     *
     * @return array{original_share: float, mirror_charge: Charge}
     */
    public function splitCharge(Charge $charge, MembershipAccount $otherAccount, Membership $otherMembership): array
    {
        $existingMirror = Charge::query()
            ->where('metadata->source_charge_id', $charge->id)
            ->whereIn('status', ['pending', 'partial'])
            ->first();

        if ($existingMirror) {
            return [
                'original_share' => round((float) $charge->balance, 2),
                'mirror_charge' => $existingMirror,
            ];
        }

        $total = round((float) $charge->balance, 2);
        $originalShare = round($total / 2, 2);
        $otherShare = round($total - $originalShare, 2);

        $charge->update([
            'amount' => $originalShare,
            'balance' => $originalShare,
            'metadata' => array_merge($charge->metadata ?? [], [
                'interclub_split' => true,
                'interclub_split_total' => $total,
            ]),
        ]);

        $mirrorCharge = Charge::create([
            'membership_account_id' => $otherAccount->id,
            'membership_id' => $otherMembership->id,
            'member_id' => $charge->member_id,
            'concept_id' => $charge->concept_id,
            'description' => $charge->description
                ? "{$charge->description} (mitad interclub)"
                : 'Mitad interclub',
            'amount' => $otherShare,
            'balance' => $otherShare,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_year' => $charge->period_year,
            'period_month' => $charge->period_month,
            'allows_partial_payments' => false,
            'status' => 'pending',
            'metadata' => [
                'interclub_split' => true,
                'interclub_split_total' => $total,
                'source_charge_id' => $charge->id,
            ],
        ]);

        return [
            'original_share' => $originalShare,
            'mirror_charge' => $mirrorCharge,
        ];
    }
}
