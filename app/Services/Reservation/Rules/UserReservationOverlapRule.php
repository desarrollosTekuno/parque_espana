<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Services\Reservation\Context\ReservationContext;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Carbon\Carbon;

class UserReservationOverlapRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $data = $context->data;
        $member = $context->member;

        $start_datetime = Carbon::parse($data['start_datetime']);
        $end_datetime = Carbon::parse($data['end_datetime']);

        // Sin filtrar por recurso: un socio no puede tener dos reservaciones en el
        // mismo horario, sea o no la misma amenidad.
        $hasOverlap = Reservation::query()
            ->where('member_id', $member->id)
            ->where('club_id', $data['club_id'])
            ->whereNotIn('reservation_status_id', [ReservationStatus::CANCELADA])
            ->where(function ($query) use ($start_datetime, $end_datetime) {
                $query
                    ->where('start_datetime', '<', $end_datetime)
                    ->where('end_datetime', '>', $start_datetime);
            })
            ->exists();

        if ($hasOverlap) {
            throw new ReservationException(
                'No puedes realizar una reservación porque ya tienes otra reservación en ese horario.'
            );
        }
    }
}