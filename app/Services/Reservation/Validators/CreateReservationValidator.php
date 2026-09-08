<?php

namespace App\Services\Reservation\Validators;

use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Rules\AdvanceDaysRule;
use App\Services\Reservation\Rules\CapacityRule;
use App\Services\Reservation\Rules\ClassRequiresCoachRule;
use App\Services\Reservation\Rules\ConsecutiveReservationRule;
use App\Services\Reservation\Rules\MinorRequiresClassRule;
use App\Services\Reservation\Rules\ReservationsPerDayRule;
use App\Services\Reservation\Rules\UserNoShowPenaltyRule;
use App\Services\Reservation\Rules\UserReservationOverlapRule;

class CreateReservationValidator
{
    protected array $rules;

    /**
     * @param bool $includeDailyLimit Aplica la regla "reservaciones_por_dia" (límite de
     *  reservaciones por día, configurable por club). Desactívala cuando una sola solicitud
     *  crea varias reservaciones ligadas entre sí (ej. jardín + asador) que deben contar
     *  como una sola reservación del día; en ese caso valida el límite una sola vez por fuera.
     */
    public function __construct(bool $includeDailyLimit = true)
    {
        $this->rules = [
            new MinorRequiresClassRule(),
            new ClassRequiresCoachRule(),
            new AdvanceDaysRule(),
            new UserNoShowPenaltyRule(),
            new UserReservationOverlapRule(),
            new ConsecutiveReservationRule(),
            new CapacityRule(),
        ];

        if ($includeDailyLimit) {
            $this->rules[] = new ReservationsPerDayRule();
        }
    }

    public function validate(ReservationContext $context): void
    {
        foreach ($this->rules as $rule) {
            $rule->validate($context);
        }
    }
}
