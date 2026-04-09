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
}
