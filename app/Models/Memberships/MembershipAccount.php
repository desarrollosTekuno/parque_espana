<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use App\Models\Members\Member;
use App\Models\Billing\Charge;
use App\Models\Billing\Payment;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipAccount extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'cancellation_type' => 'string',
    ];

    protected $table = 'memberships.accounts';

    /**
     * Filtra cuentas que tienen al menos una membresía activa y primaria en el club dado.
     * Basado en memberships.memberships (club_id).
     */
    public function scopeForClub(Builder $query, int $clubId): Builder
    {
        return $query->whereHas('memberships', function (Builder $q) use ($clubId) {
            $q->where('club_id', $clubId)
              ->where('status', 'active')
              ->where('is_primary', true);
        });
    }

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

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reactivations()
    {
        return $this->hasMany(AccountReactivation::class, 'membership_account_id');
    }

    public function latestReactivation()
    {
        return $this->hasOne(AccountReactivation::class, 'membership_account_id')
            ->latestOfMany('reactivated_at');
    }

    public function members()
    {
        return $this->belongsToMany(
            Member::class,
            'memberships.account_members',
            'membership_account_id',
            'member_id'
        );
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_account_id');
    }

    public function originAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'origin_account_id');
    }

    public function derivedAccounts()
    {
        return $this->hasMany(MembershipAccount::class, 'origin_account_id');
    }
}
