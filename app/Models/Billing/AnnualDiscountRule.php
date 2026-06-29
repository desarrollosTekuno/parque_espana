<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

class AnnualDiscountRule extends Model
{
    protected $table      = 'billing.annual_discount_rules';
    protected $connection = 'pgsql';
    protected $guarded    = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'discount_months' => 'float',
        'is_active'       => 'boolean',
    ];

    /**
     * Busca la regla aplicable dado un año y el mes en que se realiza el pago.
     * Si el socio paga en marzo y solo existen reglas para enero y febrero, no hay descuento.
     */
    public static function findApplicable(int $year, int $paymentMonth): ?self
    {
        return static::where('year', $year)
            ->where('pay_by_month', '>=', $paymentMonth)
            ->where('is_active', true)
            ->orderBy('pay_by_month')   // la regla más restrictiva (menor mes) primero
            ->first();
    }
}
