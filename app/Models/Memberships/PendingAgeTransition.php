<?php

namespace App\Models\Memberships;

use App\Models\Members\Member;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Model;

class PendingAgeTransition extends Model
{
    use SerializesDates;

    protected $table = 'memberships.pending_age_transitions';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'monthly_fee'       => 'float',
        'has_multiple_clubs' => 'boolean',
        'identified_at'     => 'datetime',
        'promoted_at'       => 'datetime',
        'dismissed_at'      => 'datetime',
    ];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function account()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function targetMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'target_membership_type_id');
    }

    public function promotedBy()
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    public function dismissedBy()
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }
}
