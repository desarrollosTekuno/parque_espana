<?php

namespace App\Services\Family;

use App\Exceptions\ReservationException;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccount;

class FamilyReservationGuard
{
    /**
     * Edad máxima (sin incluir) para que el titular pueda reservar a nombre de un integrante.
     */
    protected const MAX_MINOR_AGE = 15;

    /**
     * Resuelve a nombre de quién se crea la reservación.
     *
     * Si no se pide un integrante distinto, reserva el propio titular. Si se pide un
     * integrante, valida que pertenezca a la MISMA cuenta familiar del titular autenticado
     * (nunca de otra familia) y que sea menor de {@see self::MAX_MINOR_AGE} años.
     */
    public function resolveReservingMember(Member $holder, ?int $targetMemberId, int $clubId): Member
    {
        if (!$targetMemberId || (int) $targetMemberId === $holder->id) {
            return $holder;
        }

        $account = MembershipAccount::query()
            ->where('club_id', $clubId)
            ->whereHas('accountMembers', function ($query) use ($holder) {
                $query->where('member_id', $holder->id)->where('is_primary_holder', true);
            })
            ->with('accountMembers.member')
            ->first();

        if (!$account) {
            throw new ReservationException('Solo el socio titular puede crear reservaciones para integrantes de la familia.');
        }

        $accountMember = $account->accountMembers->firstWhere('member_id', (int) $targetMemberId);
        $targetMember = $accountMember?->member;

        if (!$targetMember) {
            throw new ReservationException('El integrante seleccionado no pertenece a tu cuenta familiar.');
        }

        if ($targetMember->age === null || $targetMember->age >= self::MAX_MINOR_AGE) {
            throw new ReservationException(
                'Solo puedes crear reservaciones a nombre de integrantes menores de ' . self::MAX_MINOR_AGE . ' años.'
            );
        }

        return $targetMember;
    }
}
