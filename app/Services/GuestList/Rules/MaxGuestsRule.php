<?php

namespace App\Services\GuestList\Rules;

use App\Exceptions\BusinessRuleException;
use App\Services\GuestList\Context\GuestListContext;
use App\Services\GuestList\Rules\GuestListRule;
use Override;

class MaxGuestsRule implements GuestListRule
{
    public function validate(GuestListContext $context): void
    {
        $data = $context->data;

        $maxGuests = $data['club_id'] == 1 ? 20 : 12;

        $guestList = $data['guests'];

        if (count($guestList) > $maxGuests)
        {
            throw new BusinessRuleException('El número máximo de invitados para el clubs es de ' . $maxGuests);
        }

    }
}
