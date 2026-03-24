<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Services\Reservation\ReservationContext;

class ConsecutiveReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $data = $context->data;
        $user = $context->user;

        // Valida que no exista una reservación antes o despues de la nueva por usuario
        $reservations = Reservation::where('user_id', $user->id)
            ->where('amenity_resource_id', $data['amenity_resource_id'])
            ->where('club_id', $data['club_id'])
            ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
            ->where(function ($query) use ($data){
                $query->where('start_datetime', '<', $data['end_datetime'])
                      ->where('end_datetime', '>', $data['start_datetime']);
            })
            ->orWhere('end_datetime', $data['start_datetime'])
            ->orWhere('start_datetime', $data['end_datetime'])
            ->count();

        if ($reservations >= 1)
        {
            throw new ReservationException('No puedes hacer reservaciones consecutivas');
        }
    }
}
