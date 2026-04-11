<?php

namespace App\Services\Reservation\Validators;

use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Rules\CancelReservationRule;

class CancelReservationValidator
{
    protected array $rules;

    public function __construct()
    {
        $this->rules = [
            new CancelReservationRule(),
        ];
    }

    public function validate(ReservationContext $context): void
    {
        foreach ($this->rules as $rule) {
            $rule->validate($context);
        }
    }

}
