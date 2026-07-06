<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Services\Reservation\Context\ReservationContext;

class UserOverlapRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $data = $context->data;
        $member = $context->member;

        //Valida que el socio no pueda reservar en el mismo horario
        $reservations = Reservation::where('member_id', $member->id)
            ->where('amenity_resource_id', $data['amenity_resource_id'])
            ->where('club_id', $data['club_id'])
            ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
            ->where(function ($query) use ($data){
                $query->where('start_datetime', '<', $data['end_datetime'])
                      ->where('end_datetime', '>', $data['start_datetime']);
            })
            ->count();

        if ($reservations >= 1)
        {
            throw new ReservationException('No puedes reservar en el mismo horario');
        }

    }
}
