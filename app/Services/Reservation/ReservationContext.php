<?php

namespace App\Services\Reservation;

class ReservationContext
{
    public array $data;
    public $amenity;
    public $amenityResource;
    public $user;

    public function __construct(array $data, $amenity, $amenityResource, $user)
    {
        $this->data = $data;
        $this->amenity = $amenity;
        $this->amenityResource = $amenityResource;
        $this->user = $user;
    }
}
