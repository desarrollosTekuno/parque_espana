<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargeConcept extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.concepts';

    protected $casts = [
        'default_amount' => 'float',
        'is_recurring' => 'boolean',
        'allows_partial_payments' => 'boolean',
        'is_mobile_payable' => 'boolean',
        'splits_between_parks' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function charges()
    {
        return $this->hasMany(Charge::class, 'concept_id');
    }

    public function clubAmounts()
    {
        return $this->hasMany(ChargeConceptClubAmount::class, 'concept_id');
    }

    public function resolveAmountForClub(Club|int|string|null $club = null): ?float
    {
        $clubId = $club instanceof Club ? $club->id : ($club !== null ? (int) $club : null);

        if ($clubId) {
            $configuredAmount = $this->relationLoaded('clubAmounts')
                ? $this->clubAmounts
                    ->first(fn (ChargeConceptClubAmount $clubAmount) => (int) $clubAmount->club_id === $clubId && $clubAmount->is_active)
                : $this->clubAmounts()
                    ->where('club_id', $clubId)
                    ->where('is_active', true)
                    ->first();

            if ($configuredAmount) {
                return (float) $configuredAmount->amount;
            }
        }

        return $this->default_amount !== null ? (float) $this->default_amount : null;
    }

    /**
     * Cuota anual del concepto (resolveAmountForClub) prorrateada por los
     * meses que quedan del año a partir de $asOf (incluyéndolo), hasta
     * diciembre. Se usa para conceptos de una sola exhibición cuyo periodo de
     * uso corre hasta fin de año sin importar cuándo se contratan (p. ej.
     * LOCKERS), para no cobrar el año completo a quien se inscribe a mitad
     * de año.
     */
    public function resolveProratedAnnualAmountForClub(Club|int|string|null $club = null, ?Carbon $asOf = null): ?float
    {
        $annualAmount = $this->resolveAmountForClub($club);

        if ($annualAmount === null) {
            return null;
        }

        $month = ($asOf ?? now())->month;
        $monthsRemaining = 12 - $month + 1;

        return round(($annualAmount / 12) * $monthsRemaining, 2);
    }
}
