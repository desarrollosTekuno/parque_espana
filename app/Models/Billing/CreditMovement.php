<?php

namespace App\Models\Billing;

use App\Models\Memberships\MembershipAccount;
use Illuminate\Database\Eloquent\Model;

class CreditMovement extends Model
{
    protected $table      = 'billing.credit_movements';
    protected $connection = 'pgsql';
    protected $guarded    = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'float',
    ];

    public function membershipAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
