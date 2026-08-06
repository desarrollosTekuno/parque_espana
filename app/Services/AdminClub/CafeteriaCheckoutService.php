<?php

namespace App\Services\AdminClub;

use App\Models\AdminClub\CafeteriaVisit;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use Illuminate\Validation\ValidationException;

/**
 * Registra la salida de una visita de cafetería: decide si el consumo
 * exenta el acceso y, si no, genera el cargo correspondiente. No abre ni
 * cierra transacción — el llamador decide cuándo confirmar (ver
 * CafeteriaVisitController::checkout(), que cobra de inmediato, y
 * CollectionController::storePayment(), que solo lo confirma al efectuar el
 * cobro junto con el resto de la cuenta).
 */
class CafeteriaCheckoutService
{
    public function checkout(CafeteriaVisit $cafeteriaVisit, float $consumption, ?int $checkedOutBy, bool $chargePending = false): ?Charge
    {
        if ($cafeteriaVisit->status === 'completed') {
            throw ValidationException::withMessages([
                'messageError' => 'Este ingreso ya fue procesado.',
            ]);
        }

        $minConsumption = (float) $cafeteriaVisit->min_consumption;
        $waived = $consumption >= $minConsumption;
        $charge = null;

        if (!$waived) {
            $concept = ChargeConcept::where('code', 'CAFETERIA_PASS')->firstOrFail();

            $charge = Charge::create([
                'membership_account_id'   => null,
                'membership_id'           => null,
                'member_id'               => null,
                'concept_id'              => $concept->id,
                'description'             => 'Acceso a cafetería para ' . $cafeteriaVisit->visitor_name,
                'status'                  => $chargePending ? 'pending' : 'paid',
                'amount'                  => $minConsumption,
                'balance'                 => $chargePending ? $minConsumption : 0,
                'allows_partial_payments' => false,
                'metadata'                => [
                    'cafeteria_visit_id' => $cafeteriaVisit->id,
                    'visitor_name'       => $cafeteriaVisit->visitor_name,
                    'document_type'      => $cafeteriaVisit->document_type,
                    'document_number'    => $cafeteriaVisit->document_number,
                    'consumption_amount' => $consumption,
                ],
            ]);
        }

        $cafeteriaVisit->update([
            'consumption_amount'   => $consumption,
            'access_fee_waived'    => $waived,
            'access_fee_charged'   => $waived ? null : $minConsumption,
            'checked_out_at'       => now()->setTimezone('America/Mexico_City'),
            'document_returned'    => true,
            'document_returned_at' => now()->setTimezone('America/Mexico_City'),
            'status'               => 'completed',
            'checked_out_by'       => $checkedOutBy,
        ]);

        return $charge;
    }
}
