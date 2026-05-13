<?php

namespace App\Services\GuestList\Rules;

use App\Exceptions\BusinessRuleException;
use App\Models\AdminClub\GuestListVariable;
use App\Services\GuestList\Context\GuestListContext;
use App\Services\GuestList\Rules\GuestListRule;
use Override;

class MaxGuestsRule implements GuestListRule
{
    public function validate(GuestListContext $context): void
    {
        $data = $context->data;

        $maxGuests = GuestListVariable::where('code', 'MAX_GUESTS')
            ->where('club_id', $data['club_id'])
            ->value('value');

        if (is_null($maxGuests))
        {
            throw new BusinessRuleException('No se ha configurado el número máximo de invitados para el club');
        }

        $guestList = $data['guests'];

        if (count($guestList) > $maxGuests)
        {
            throw new BusinessRuleException('El número máximo de invitados para el clubs es de ' . $maxGuests);
        }

    }
}
