<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\Payment;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipAccount extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.accounts';

    public function accountGroup()
    {
        return $this->belongsTo(MembershipAccountGroup::class, 'account_group_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class, 'membership_account_id');
    }

    public function accountMembers()
    {
        return $this->hasMany(MembershipAccountMember::class, 'membership_account_id');
    }

    public function primaryHolder()
    {
        return $this->hasOne(MembershipAccountMember::class, 'membership_account_id')
            ->where('is_primary_holder', true);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class, 'membership_account_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'membership_account_id');
    }

    public function absencePermits()
    {
        return $this->hasMany(AbsencePermit::class, 'membership_account_id');
    }
}
