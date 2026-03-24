<?php

namespace App\Services\Reservation\Rules;

interface ReservationRule
{
    public function validate(array $data, $amenity, $amenityResource): void;
}

