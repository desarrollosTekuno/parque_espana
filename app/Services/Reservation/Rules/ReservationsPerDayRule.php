<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\SystemVariable;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Services\Reservation\Context\ReservationContext;
use Carbon\Carbon;

class ReservationsPerDayRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $data = $context->data;
        $member = $context->member;

        $limit = SystemVariable::where('club_id', $data['club_id'])
            ->where('name', 'reservaciones_por_dia')
            ->value('value');

        // Si el club no tiene configurada la variable, no se aplica la regla.
        if (!$limit) {
            return;
        }

        $date = Carbon::parse($data['start_datetime'])->toDateString();

        $reservations = Reservation::where('member_id', $member->id)
            ->where('club_id', $data['club_id'])
            ->whereDate('start_datetime', $date)
            ->where('reservation_status_id', ReservationStatus::ACTIVA)
            ->count();

        if ($reservations >= (int) $limit) {
            throw new ReservationException(
                "Solo puedes realizar {$limit} reservación(es) por día."
            );
        }
    }
}