<?php

namespace App\Services\Reservation\Context;

class ReservationContext
{
    public array $data;
    public $amenity;
    public $amenityResource;
    public $user;
    public $reservation;

    public function __construct(array $data = [], $amenity = null, $amenityResource = null, $user = null, $reservation = null)
    {
        $this->data = $data;
        $this->amenity = $amenity;
        $this->amenityResource = $amenityResource;
        $this->user = $user;
        $this->reservation = $reservation;
    }
}
