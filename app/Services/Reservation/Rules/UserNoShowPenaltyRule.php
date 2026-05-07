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
        $hoursPenalty = (int) SystemVariable::where('name', 'horas_suspension_reserva')->first()->value;

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
                    $lastNoShowDate = Carbon::parse($reservation->end_datetime);
                }

                $consecutiveNoShows++;

            } else {

                // solo rompe si ya empezaste a contar
                if ($foundFirstNoShow) {
                    break;
                }
            }
        }

        if ($consecutiveNoShows >= 2) {

            $unlockDate = $lastNoShowDate->copy()->addHours($hoursPenalty);

            if (now()->lt($unlockDate)) {

                throw new ReservationException(
                    'No puedes reservar debido a inasistencias recientes. Podrás reservar nuevamente a partir del '
                    . $unlockDate->format('Y-m-d') . ' a las ' . $unlockDate->format('h:i A')
                );
            }
        }
    }
}
