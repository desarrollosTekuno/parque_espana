<?php

namespace App\Models\Members;

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
}
