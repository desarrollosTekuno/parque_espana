<?php

namespace App\Models\Memberships;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipAccount extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.accounts';

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
}
