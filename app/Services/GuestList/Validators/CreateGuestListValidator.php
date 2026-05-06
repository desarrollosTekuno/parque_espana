<?php

namespace App\Services\GuestList\Validators;

use App\Services\GuestList\Context\GuestListContext;

class CreateGuestListValidator
{
    protected array $rules;

    public function __construct()
    {
        $this->rules = [

        ];
    }

    public function validate(GuestListContext $context): void
    {
        foreach ($this->rules as $rule) {
            $rule->validate($context);
        }
    }
}
