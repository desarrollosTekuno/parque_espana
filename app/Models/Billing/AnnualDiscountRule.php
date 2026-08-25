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
     * Busca la regla aplicable dado el mes en que se realiza el pago — el
     * patrón de descuento (enero = mes completo, febrero = medio mes, etc.)
     * no depende del año, aplica igual siempre. Si el socio paga en marzo y
     * solo existen reglas para enero y febrero, no hay descuento.
     */
    public static function findApplicable(int $paymentMonth): ?self
    {
        return static::where('pay_by_month', '>=', $paymentMonth)
            ->where('is_active', true)
            ->orderBy('pay_by_month')   // la regla más restrictiva (menor mes) primero
            ->first();
    }
}
