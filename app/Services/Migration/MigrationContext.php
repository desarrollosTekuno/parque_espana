<?php

namespace App\Services\Migration;

/**
 * Contexto compartido entre todos los importadores.
 * Se va llenando progresivamente a medida que cada importador corre.
 */
class MigrationContext
{
    /** ID_ORIGEN (Excel) → member.id */
    public array $membersByOriginId = [];

    /** NO_CUENTA (Excel) → membership_account.id */
    public array $accountsByNumber = [];

    /** "{NO_CUENTA}_{club_id}" → membership.id */
    public array $membershipByAccountAndClub = [];

    /** Club code → club.id  (pre-cargado) */
    public array $clubsByCode = [];

    /** MembershipType code → membership_type.id  (pre-cargado) */
    public array $membershipTypesByCode = [];

    /** ChargeConcept code → concept.id  (pre-cargado) */
    public array $conceptsByCode = [];

    /** PaymentMethod code → payment_method.id  (pre-cargado) */
    public array $paymentMethodsByCode = [];

    /** GRUPO_FAMILIAR code → account_group.id */
    public array $groupsByCode = [];

    /** NO_CUENTA → member_id del titular principal */
    public array $primaryHolderByAccount = [];

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function clubId(string $code): ?int
    {
        return $this->clubsByCode[strtoupper(trim($code))] ?? null;
    }

    public function membershipTypeId(string $code): ?int
    {
        return $this->membershipTypesByCode[strtoupper(trim($code))] ?? null;
    }

    public function conceptId(string $code): ?int
    {
        return $this->conceptsByCode[strtoupper(trim($code))] ?? null;
    }

    public function paymentMethodId(string $code): ?int
    {
        return $this->paymentMethodsByCode[strtoupper(trim($code))] ?? null;
    }

    public function accountId(string $number): ?int
    {
        return $this->accountsByNumber[trim($number)] ?? null;
    }

    public function memberId(string $originId): ?int
    {
        return $this->membersByOriginId[trim($originId)] ?? null;
    }

    public function membershipId(string $accountNumber, int $clubId): ?int
    {
        $key = "{$accountNumber}_{$clubId}";
        return $this->membershipByAccountAndClub[$key] ?? null;
    }
}
