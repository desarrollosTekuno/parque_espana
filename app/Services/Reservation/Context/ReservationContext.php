<?php

namespace App\Services\Reservation\Context;

class ReservationContext
{
    public array $data;
    public $amenity;
    public $amenityResource;
    public $member;
    public $user;
    public $reservation;
    public array $excludeReservationIds;

    public function __construct(array $data = [], $amenity = null, $amenityResource = null, $member = null, $user = null, $reservation = null, array $excludeReservationIds = [])
    {
        $this->data = $data;
        $this->amenity = $amenity;
        $this->amenityResource = $amenityResource;
        $this->member = $member;
        $this->user = $user;
        $this->reservation = $reservation;
        $this->excludeReservationIds = $excludeReservationIds;
    }
}
