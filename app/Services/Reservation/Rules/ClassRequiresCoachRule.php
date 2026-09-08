<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Services\Reservation\Context\ReservationContext;

class ClassRequiresCoachRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $isClass = (bool) ($context->data['is_class'] ?? false);

        if (!$isClass) {
            return;
        }

        if (empty($context->data['coach_id'])) {
            throw new ReservationException('Debes seleccionar un profesor para reservar una clase.');
        }
    }
}
