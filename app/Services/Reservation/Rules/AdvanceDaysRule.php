<?php

namespace App\Services\Reservation\Rules;

use App\Exceptions\ReservationException;
use App\Models\AdminClub\SystemVariable;
use App\Services\Reservation\ReservationContext;
use Carbon\Carbon;

class AdvanceDaysRule implements ReservationRule
{
    public function validate(ReservationContext $context): void
    {
        $data = $context->data;

        // valida los dias de anticipacion para poder reservar
        $days = SystemVariable::where('name', 'dias_para_crear_reserva')->first()->value;

        $startDate = Carbon::parse($data['start_datetime'])->startOfDay();
        $today = Carbon::now()->startOfDay();

        $maxDate = $today->copy()->addDays($days - 1);

        if ($startDate->gt($maxDate))
        {
            throw new ReservationException('Solo puedes reservar hasta ' . $days . ' dias a partir de hoy');
        }

    }
}
