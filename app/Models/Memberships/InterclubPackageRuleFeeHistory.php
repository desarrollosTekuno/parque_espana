<?php

namespace App\Models\Memberships;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterclubPackageRuleFeeHistory extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.interclub_package_rule_fee_history';

    protected $casts = [
        'year' => 'integer',
        'monthly_fee' => 'float',
        'inscription_fee' => 'float',
    ];

    public function interclubPackageRule()
    {
        return $this->belongsTo(InterclubPackageRule::class, 'interclub_package_rule_id');
    }
}
