<?php

namespace App\Models\Billing;

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
}
