<?php

namespace App\Services\GuestList\Context;

class GuestListContext
{
    public array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
}
