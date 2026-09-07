<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Models\AdminClub\SystemVariable;
use App\Services\Reservation\Context\ReservationContext;
use Carbon\Carbon;

class CancellationsPerWeekRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $reservation = $context->reservation;

        $limit = SystemVariable::where('club_id', $reservation->club_id)
            ->where('name', 'cancelaciones_por_semana')
            ->value('value');

        // Si el club no tiene configurada la variable, no se aplica la regla.
        if (!$limit) {
            return;
        }

        $tz = 'America/Mexico_City';
        $weekStart = Carbon::now($tz)->startOfWeek();
        $weekEnd = Carbon::now($tz)->endOfWeek();

        $cancellations = Reservation::where('member_id', $reservation->member_id)
            ->where('club_id', $reservation->club_id)
            ->where('reservation_status_id', ReservationStatus::CANCELADA)
            ->whereBetween('cancelled_at', [$weekStart, $weekEnd])
            ->count();

        if ($cancellations >= (int) $limit) {
            throw new ReservationException(
                "Solo puedes cancelar {$limit} reservación(es) por semana."
            );
        }
    }
}
