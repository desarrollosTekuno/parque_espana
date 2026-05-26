<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipAccount;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpeiOrder extends Model
{
    use HasFactory, SerializesDates;

    protected $table = 'billing.spei_orders';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount'       => 'float',
        'expires_at'   => 'datetime',
        'applications' => 'array',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

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

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
