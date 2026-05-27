<?php

namespace App\Services\Reservation\Context;

class ReservationContext
{
    public array $data;
    public $amenity;
    public $amenityResource;
    public $member;
    public $reservation;

    public function __construct(array $data = [], $amenity = null, $amenityResource = null, $member = null, $reservation = null)
    {
        $this->data = $data;
        $this->amenity = $amenity;
        $this->amenityResource = $amenityResource;
        $this->member = $member;
        $this->reservation = $reservation;
    }
}
