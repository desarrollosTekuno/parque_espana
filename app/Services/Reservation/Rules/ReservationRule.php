<?php

namespace App\Services\Reservation\Rules;

use App\Services\Reservation\Context\ReservationContext;

interface ReservationRule
{
    public function validate(ReservationContext $context): void;
}

