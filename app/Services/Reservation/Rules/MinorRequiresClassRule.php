<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Services\Reservation\Context\ReservationContext;

class MinorRequiresClassRule implements ReservationRule
{
    private const MAX_AGE_REQUIRES_CLASS = 15;

    public function validate(ReservationContext $context): void
    {
        $member = $context->member;

        if (!$member || $member->age === null || $member->age >= self::MAX_AGE_REQUIRES_CLASS) {
            return;
        }

        $isClass = (bool) ($context->data['is_class'] ?? false);

        if (!$isClass) {
            throw new ReservationException('Los integrantes menores de 15 años solo pueden reservar amenidades para una clase.');
        }
    }
}
