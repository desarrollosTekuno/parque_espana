<?php

namespace App\Services\GuestList\Rules;

use App\Services\GuestList\Context\GuestListContext;

interface GuestListRule
{
    public function validate(GuestListContext $context): void;
}
