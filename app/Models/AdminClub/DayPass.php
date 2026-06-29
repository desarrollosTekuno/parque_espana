<?php

namespace App\Models\AdminClub;

use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DayPass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'guest_lists.day_passes';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount'         => 'float',
        'paid_at'        => 'datetime',
        'ticket_sent_at' => 'datetime',
        'date'           => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function visitors()
    {
        return $this->hasMany(DayPassVisitor::class, 'day_pass_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
