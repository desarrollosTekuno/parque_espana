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

    public function getResolvedMonthlyFeeTotalAttribute(): float
    {
        return round((float) ($this->monthly_fee_total ?? $this->monthly_fee ?? 0), 2);
    }

    public function getResolvedMonthlyFeeShareAttribute(): float
    {
        return round((float) ($this->monthly_fee_share ?? $this->monthly_fee ?? 0), 2);
    }
}
