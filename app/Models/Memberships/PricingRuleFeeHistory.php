<?php

namespace App\Models\Memberships;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRuleFeeHistory extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.pricing_rule_fee_history';

    protected $casts = [
        'year' => 'integer',
        'monthly_fee' => 'float',
        'inscription_fee' => 'float',
    ];

    public function pricingRule()
    {
        return $this->belongsTo(PricingRule::class, 'pricing_rule_id');
    }
}
