<?php

namespace App\Models\Billing;

use App\Models\Memberships\MembershipAccount;
use Illuminate\Database\Eloquent\Model;

class CreditBalance extends Model
{
    protected $table      = 'billing.credit_balances';
    protected $connection = 'pgsql';
    protected $guarded    = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'float',
    ];

    public function membershipAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function movements()
    {
        return $this->hasMany(CreditMovement::class, 'membership_account_id', 'membership_account_id');
    }

    /** Obtiene o crea el registro de saldo para una cuenta. */
    public static function forAccount(int $accountId): self
    {
        return static::firstOrCreate(
            ['membership_account_id' => $accountId],
            ['amount' => 0]
        );
    }
}
