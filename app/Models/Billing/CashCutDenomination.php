<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

class CashCutDenomination extends Model
{
    protected $table = 'billing.cash_cut_denominations';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'denomination' => 'float',
        'quantity' => 'integer',
        'subtotal' => 'float',
    ];

    public function cashCut()
    {
        return $this->belongsTo(CashCut::class, 'cash_cut_id');
    }
}
