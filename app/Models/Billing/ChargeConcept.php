<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
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
}
