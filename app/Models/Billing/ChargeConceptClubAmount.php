<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargeConceptClubAmount extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.concept_club_amounts';

    protected $casts = [
        'amount' => 'float',
        'applies_iva' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function concept()
    {
        return $this->belongsTo(ChargeConcept::class, 'concept_id')->withTrashed();
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
