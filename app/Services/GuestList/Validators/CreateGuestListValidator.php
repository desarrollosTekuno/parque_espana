<?php

namespace App\Services\GuestList\Validators;

use App\Services\GuestList\Context\GuestListContext;
use App\Services\GuestList\Rules\MaxGuestsRule;

class CreateGuestListValidator
{
    protected array $rules;

    public function __construct()
    {
        $this->rules = [
            new MaxGuestsRule(),
        ];
    }

    public function validate(GuestListContext $context): void
    {
        foreach ($this->rules as $rule) {
            $rule->validate($context);
        }
    }
}
