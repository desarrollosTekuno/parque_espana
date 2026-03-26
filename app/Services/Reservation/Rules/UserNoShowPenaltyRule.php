<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Models\AdminClub\SystemVariable;
use App\Services\Reservation\Context\ReservationContext;
use Carbon\Carbon;

class UserNoShowPenaltyRule
{
    public function validate(ReservationContext $context): void
    {
        $user = $context->user;

        // Valida que el usuario no tenga dos inasistencias seguidas
        $daysPenalty = (int) SystemVariable::where('name', 'dias_suspension_reserva')->first()->value;

        $reservations = Reservation::where('user_id', $user->id)
            ->where('start_datetime', '<', now())
            ->orderBy('start_datetime', 'desc')
            ->limit(40)
            ->get();

        $consecutiveNoShows = 0;
        $lastNoShowDate = null;
        $foundFirstNoShow = false;

        foreach ($reservations as $reservation) {

            if ($reservation->reservation_status_id == ReservationStatus::INASISTENCIA) {

                if (!$foundFirstNoShow) {
                    $foundFirstNoShow = true;
                    $lastNoShowDate = Carbon::parse($reservation->start_datetime);
                }

                $consecutiveNoShows++;

            } else {

                // solo rompe si ya empezaste a contar
                if ($foundFirstNoShow) {
                    break;
                }

                // si no has encontrado ninguna inasistencia, sigue buscando
            }
        }

        if ($consecutiveNoShows >= 2) {

            $unlockDate = $lastNoShowDate->copy()->addDays($daysPenalty);

            if (now()->lt($unlockDate)) {

                throw new ReservationException(
                    'No puedes reservar debido a inasistencias recientes. Podrás reservar nuevamente a partir de ' 
                    . $unlockDate->format('Y-m-d')
                );
            }
        }
    }
}
