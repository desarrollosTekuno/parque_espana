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

        // valida los dias de anticipacion para cancelar una reservacion
        $days = SystemVariable::where('name', 'dias_para_cancelar_reserva')->first()->value;

        $startDate = Carbon::parse($reservation->start_datetime)->startOfDay();
        $today = Carbon::now()->startOfDay();

        $limitDate = $startDate->copy()->subDays($days);

        if ($today->gt($limitDate))
        {
            throw new ReservationException('No puedes cancelar una reservación con menos de ' . $days . ' días de anticipación');
        }
    }
}
