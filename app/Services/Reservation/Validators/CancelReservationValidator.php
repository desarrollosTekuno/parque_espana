<?php

namespace App\Services\Reservation\Validators;

use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Rules\CancelDeadlineHourRule;
use App\Services\Reservation\Rules\CancelReservationRule;
use App\Services\Reservation\Rules\CancellationsPerWeekRule;

class CancelReservationValidator
{
    /**
     * Código del club (Parque España II) con reglas de cancelación propias:
     * límite de horario del día anterior + una cancelación por semana.
     */
    private const PE2_CLUB_CODE = 'PE2';

    public function validate(ReservationContext $context): void
    {
        foreach ($this->resolveRules($context) as $rule) {
            $rule->validate($context);
        }
    }

    private function resolveRules(ReservationContext $context): array
    {
        $clubCode = $context->reservation?->club?->code;

        if ($clubCode === self::PE2_CLUB_CODE) {
            return [
                new CancelDeadlineHourRule(),
                new CancellationsPerWeekRule(),
            ];
        }

        return [
            new CancelReservationRule(),
        ];
    }
}
