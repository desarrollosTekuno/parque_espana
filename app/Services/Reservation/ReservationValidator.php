<?php

namespace App\Services\Reservation;

use App\Services\Reservation\Rules\CapacityRule;

class ReservationValidator
{
    protected array $rules;

    public function __construct()
    {
        $this->rules = [
            new CapacityRule(),
        ];
    }

    public function validate(array $data, $amenity, $amenityResource): void
    {
        foreach ($this->rules as $rule) {
            $rule->validate($data, $amenity, $amenityResource);
        }
    }
}
