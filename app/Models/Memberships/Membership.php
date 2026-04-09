<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.memberships';

    public function account()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function originMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'origin_membership_type_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
