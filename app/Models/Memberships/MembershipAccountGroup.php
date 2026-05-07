<?php

namespace App\Models\Memberships;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipAccountGroup extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'memberships.account_groups';

    public function accounts()
    {
        return $this->hasMany(MembershipAccount::class, 'account_group_id');
    }

    public function absencePermits()
    {
        return $this->hasMany(AbsencePermit::class, 'account_group_id');
    }
}
