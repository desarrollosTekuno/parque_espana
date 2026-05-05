<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Services\Reservation\Context\ReservationContext;

class CapacityRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $data = $context->data;
        $amenity = $context->amenity;
        $amenityResource = $context->amenityResource;

        // Valida que no exista una reservación en el mismo horario
        $reservations = Reservation::where('amenity_resource_id', $data['amenity_resource_id'])
            ->where('club_id', $data['club_id'])
            ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
            ->where(function ($query) use ($data){
                $query->where('start_datetime', '<', $data['end_datetime'])
                      ->where('end_datetime', '>', $data['start_datetime']);
            })
            ->count();

        if ($amenity->reservation_type == 'daily' && $reservations >= 1)
        {
            throw new ReservationException('Ya no hay capacidad disponible para esta amenidad en este horario');
        }

        if ($amenity->reservation_type != 'daily' && $reservations >= $amenityResource->capacity)
        {
            throw new ReservationException('Ya no hay capacidad disponible para esta amenidad en este horario');
        }
    }
}
