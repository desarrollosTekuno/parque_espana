<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.payments';

    protected $casts = [
        'amount' => 'float',
        'subtotal' => 'float',
        'iva' => 'float',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function membershipAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function applications()
    {
        return $this->hasMany(PaymentApplication::class, 'payment_id');
    }
}
