<?php

namespace App\Services\Reservation;

use App\Services\Reservation\Rules\AdvanceDaysRule;
use App\Services\Reservation\Rules\CapacityRule;
use App\Services\Reservation\Rules\ConsecutiveReservationRule;
use App\Services\Reservation\Rules\UserOverlapRule;

class ReservationValidator
{
    protected array $rules;

    public function __construct()
    {
        $this->rules = [
            new AdvanceDaysRule(),
            new UserOverlapRule(),
            new ConsecutiveReservationRule(),
            new CapacityRule(),
        ];
    }

    public function validate(ReservationContext $context): void
    {
        foreach ($this->rules as $rule) {
            $rule->validate($context);
        }
    }
}
