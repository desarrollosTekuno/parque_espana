<?php

namespace App\Models\Billing;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentApplication extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.payment_applications';

    protected $casts = [
        'applied_amount' => 'float',
        'subtotal' => 'float',
        'iva' => 'float',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id');
    }
}
