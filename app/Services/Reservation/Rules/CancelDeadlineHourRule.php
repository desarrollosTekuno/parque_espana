<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Services\Reservation\Context\ReservationContext;
use Carbon\Carbon;

/**
 * Regla de cancelación específica del Parque España II: la reservación solo puede
 * cancelarse hasta 60 minutos antes de las 19:00 horas del día anterior al de la
 * reservación (es decir, a más tardar a las 18:00 horas del día previo).
 */
class CancelDeadlineHourRule implements ReservationRule
{
    private const DEADLINE_HOUR = 19;
    private const MINUTES_BEFORE_DEADLINE = 60;

    public function validate(ReservationContext $context): void
    {
        $reservation = $context->reservation;
        $tz = 'America/Mexico_City';

        $startDate = Carbon::parse($reservation->start_datetime, 'UTC')->setTimezone($tz);

        $deadline = $startDate->copy()
            ->subDay()
            ->setTime(self::DEADLINE_HOUR, 0, 0)
            ->subMinutes(self::MINUTES_BEFORE_DEADLINE);

        if (Carbon::now($tz)->gte($deadline)) {
            throw new ReservationException(
                'La cancelación debe realizarse a más tardar a las '
                . $deadline->format('H:i') . ' horas del día anterior a la reservación.'
            );
        }
    }
}
