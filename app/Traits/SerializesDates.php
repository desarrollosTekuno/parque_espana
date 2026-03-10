<?php
namespace App\Traits;

trait SerializesDates
{
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format(DATE_ATOM);
    }
}