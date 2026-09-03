<?php

namespace App\Models\Memberships;

use App\Models\Billing\ChargeConcept;
use App\Models\Members\Member;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsencePermit extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.absence_permits';

    protected $casts = [
        'charge_percentage' => 'float',
        'blocks_facility_access' => 'boolean',
        'blocks_reservations' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function accountGroup()
    {
        return $this->belongsTo(MembershipAccountGroup::class, 'account_group_id');
    }

    public function membershipAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function primaryMember()
    {
        return $this->belongsTo(Member::class, 'primary_member_id');
    }

    public function chargeConcept()
    {
        return $this->belongsTo(ChargeConcept::class, 'charge_concept_id');
    }
}
