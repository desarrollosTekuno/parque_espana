<?php

namespace App\Models\Billing;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.payment_methods';

    protected $casts = [
        'requires_reference' => 'boolean',
        'requires_bank_name' => 'boolean',
        'requires_check_number' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function clubPaymentMethods()
    {
        return $this->hasMany(ClubPaymentMethod::class, 'payment_method_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
    }
}
