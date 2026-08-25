<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Model;

class ClinicalHistory extends Model
{
    protected $table = 'members.clinical_histories';
    protected $connection = 'pgsql';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'has_diabetes'          => 'boolean',
        'has_heart_condition'   => 'boolean',
        'has_epilepsy'          => 'boolean',
        'has_asthma'            => 'boolean',
        'has_allergy'           => 'boolean',
        'takes_medication'      => 'boolean',
        'has_allergens'         => 'boolean',
        'normal_blood_pressure' => 'boolean',
        'has_hypertension'      => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
