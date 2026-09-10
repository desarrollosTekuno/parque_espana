<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use App\Models\Members\Member;
use App\Models\Members\LockerAssignment;
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
        'billing_backfill_floor' => 'date',
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

    public function fiscalData()
    {
        return $this->hasOne(AccountFiscalData::class, 'membership_account_id');
    }

    public function currentLockerAssignments()
    {
        return $this->hasManyThrough(
            LockerAssignment::class,
            MembershipAccountMember::class,
            'membership_account_id',
            'member_id',
            'id',
            'member_id'
        )->where('members.locker_assignments.year', now()->year);
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

    /**
     * true solo si el grupo de esta cuenta (mismo titular con cuenta en más
     * de un parque, ver account_group_id) representa un paquete interclub
     * REAL — no basta con que el titular tenga membresía activa en más de
     * un club: si combina, por ejemplo, una Individual del parque 1 con un
     * Pase Mensual Individual del parque 2 sin relación de precio entre
     * ambas, cada una sigue siendo independiente y sí se puede pagar por
     * separado desde la app (mismo criterio que
     * MembershipPricingService::recalculateGroupFeesAfterCancellation: solo
     * cuenta si hay interclub_package_rule_id, billing_split_mode
     * equal_split, o una pricing rule marcada requires_multiple_clubs).
     * El pago de un combo real se hace directo en caja — ver
     * MemberProfileController, ChargePaymentController y
     * SpeiPaymentController.
     */
    public function spansMultipleClubs(): bool
    {
        if (!$this->account_group_id) {
            return false;
        }

        $groupMemberships = Membership::query()
            ->with('pricingRule')
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('account', fn (Builder $q) => $q->where('account_group_id', $this->account_group_id))
            ->get();

        if ($groupMemberships->pluck('club_id')->filter()->unique()->count() <= 1) {
            return false;
        }

        return $groupMemberships->contains(
            fn (Membership $m) => $m->interclub_package_rule_id !== null
                || $m->billing_split_mode === 'equal_split'
                || (bool) $m->pricingRule?->requires_multiple_clubs
        );
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

    public function cancellationReason()
    {
        return $this->belongsTo(\App\Models\Catalogs\CancellationReason::class, 'cancellation_reason_id');
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
