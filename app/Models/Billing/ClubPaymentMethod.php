<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubPaymentMethod extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.club_payment_methods';

    protected $casts = [
        'is_active' => 'boolean',
        'conekta_secret_key' => 'encrypted',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
