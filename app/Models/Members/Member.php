<?php

namespace App\Models\Members;

use App\Models\Billing\Charge;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Memberships\MembershipAccountMember;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'members.members';

    public function accountMemberships()
    {
        return $this->hasMany(MembershipAccountMember::class, 'member_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class, 'member_id');
    }

    public function primaryAddress()
    {
        return $this->hasOne(Address::class, 'member_id')->where('is_primary', true);
    }

    public function employmentInfo()
    {
        return $this->hasOne(EmploymentInfo::class, 'member_id');
    }

    public function nationality()
    {
        return $this->belongsTo(Country::class, 'nationality_id');
    }

    public function maritalStatus()
    {
        return $this->belongsTo(MaritalStatus::class, 'marital_status_id');
    }

    public function charges()
    {
        return $this->hasMany(Charge::class, 'member_id');
    }
}
