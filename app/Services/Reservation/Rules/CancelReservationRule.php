<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\SystemVariable;
use App\Services\Reservation\Context\ReservationContext;
use Carbon\Carbon;

class CancelReservationRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $reservation = $context->reservation;

        $days = (int) SystemVariable::where('name', 'dias_para_cancelar_reserva')->value('value');

        $tz = 'America/Mexico_City';

        $startDate = Carbon::parse($reservation->start_datetime, 'UTC')
            ->setTimezone($tz)
            ->startOfDay();

        $today = Carbon::now($tz)->startOfDay();

        if ($startDate->lte($today)) {
            throw new ReservationException('No puedes cancelar una reservación del mismo día o pasada');
        }

        $diffDays = $today->diffInDays($startDate);
        $diasContandoHoy = $diffDays + 1;

        if ($diasContandoHoy < $days) {
            throw new ReservationException(
                'No puedes cancelar una reservación con menos de ' . $days . ' días de anticipación'
            );
        }
    }
}