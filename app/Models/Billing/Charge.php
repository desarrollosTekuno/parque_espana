<?php

namespace App\Models\Billing;

use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.charges';

    protected $casts = [
        'amount' => 'float',
        'balance' => 'float',
        'allows_partial_payments' => 'boolean',
        'metadata' => 'array',
    ];

    public function membershipAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function concept()
    {
        // withTrashed(): un concepto archivado (soft delete, ver
        // BillingConceptController::destroy) no debe dejar sin nombre a los
        // cargos históricos que ya lo usaban (tickets, reportes, etc.).
        return $this->belongsTo(ChargeConcept::class, 'concept_id')->withTrashed();
    }

    public function paymentApplications()
    {
        return $this->hasMany(PaymentApplication::class, 'charge_id');
    }
}
