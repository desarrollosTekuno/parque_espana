<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.memberships';

    protected $casts = [
        'is_primary' => 'boolean',
        'is_billable' => 'boolean',
        'monthly_fee' => 'float',
        'monthly_fee_total' => 'float',
        'monthly_fee_share' => 'float',
    ];

    protected $appends = [
        'resolved_monthly_fee_total',
        'resolved_monthly_fee_share',
    ];

    public function account()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function originMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'origin_membership_type_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function charges()
    {
        return $this->hasMany(Charge::class, 'membership_id');
    }

    public function pricingRule()
    {
        return $this->belongsTo(PricingRule::class, 'pricing_rule_id');
    }

    public function interclubPackageRule()
    {
        return $this->belongsTo(InterclubPackageRule::class, 'interclub_package_rule_id');
    }

    /**
     * Cuota vigente según la regla que originó esta membresía (si hay una
     * enlazada), para que un cambio de precio a mitad de año se refleje sin
     * necesidad de re-tramitar la membresía. Null si no hay regla enlazada
     * o la regla no tiene historial de montos.
     */
    public function resolveLiveMonthlyFee(?int $year = null): ?float
    {
        if ($this->interclub_package_rule_id && $this->interclubPackageRule) {
            return $this->interclubPackageRule->resolveMonthlyFee($year);
        }

        if ($this->pricing_rule_id && $this->pricingRule) {
            return $this->pricingRule->resolveMonthlyFee($year);
        }

        return null;
    }

    public function getResolvedMonthlyFeeTotalAttribute(): float
    {
        $live = $this->resolveLiveMonthlyFee();

        return round((float) ($live ?? $this->monthly_fee_total ?? $this->monthly_fee ?? 0), 2);
    }

    public function getResolvedMonthlyFeeShareAttribute(): float
    {
        $live = $this->resolveLiveMonthlyFee();

        return round((float) ($live ?? $this->monthly_fee_share ?? $this->monthly_fee ?? 0), 2);
    }
}
