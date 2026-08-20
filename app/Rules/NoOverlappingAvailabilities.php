<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoOverlappingAvailabilities implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $slots = array_values($value);

        for ($i = 0; $i < count($slots); $i++) {
            for ($j = $i + 1; $j < count($slots); $j++) {
                if ($this->overlaps($slots[$i], $slots[$j])) {
                    $fail('No se pueden agregar horarios que se traslapen en el mismo día.');
                    return;
                }
            }
        }
    }

    protected function overlaps(array $a, array $b): bool
    {
        if (($a['day_of_week'] ?? null) !== ($b['day_of_week'] ?? null)) {
            return false;
        }

        return ($a['start_time'] ?? null) < ($b['end_time'] ?? null)
            && ($b['start_time'] ?? null) < ($a['end_time'] ?? null);
    }
}
