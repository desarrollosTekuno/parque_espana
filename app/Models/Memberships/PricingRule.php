<?php

namespace App\Models\Memberships;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table= 'memberships.pricing_rules';

    protected $casts = [
        'requires_origin_family' => 'boolean',
        'requires_multiple_clubs' => 'boolean',
        'monthly_fee' => 'float',
        'inscription_fee' => 'float',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function fromMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'from_membership_type_id');
    }
}
