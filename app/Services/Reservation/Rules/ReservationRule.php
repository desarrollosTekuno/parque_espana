<?php

namespace App\Services\Reservation\Rules;

use App\Services\Reservation\ReservationContext;

interface ReservationRule
{
    public function validate(ReservationContext $context): void;
}

