<?php

namespace App\Services\Reservation\Rules;

use App\Services\GuestList\Context\GuestListContext;

interface GuestList
{
    public function validate(GuestListContext $context): void;
}
