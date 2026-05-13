<?php

namespace App\Models\Memberships;

use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Model;

class AccountReactivation extends Model
{
    use SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.account_reactivations';

    protected $casts = [
        'reactivated_at' => 'datetime',
        'requires_documentation' => 'boolean',
        'enrollment_fee_applied' => 'boolean',
        'enrollment_fee_amount' => 'float',
    ];

    public function account()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function reactivatedBy()
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }
}
