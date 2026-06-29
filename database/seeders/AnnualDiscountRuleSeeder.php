<?php

namespace Database\Seeders;

use App\Models\Billing\AnnualDiscountRule;
use Illuminate\Database\Seeder;

class AnnualDiscountRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // 2025: solo se puede pagar en enero con descuento de mes completo
            [
                'year'            => 2025,
                'pay_by_month'    => 1,
                'discount_months' => 1.0,
                'free_month'      => 12,
                'is_active'       => true,
            ],

            // 2026: enero = mes completo de descuento, febrero = medio mes de descuento
            [
                'year'            => 2026,
                'pay_by_month'    => 1,
                'discount_months' => 1.0,
                'free_month'      => 12,
                'is_active'       => true,
            ],
            [
                'year'            => 2026,
                'pay_by_month'    => 2,
                'discount_months' => 0.5,
                'free_month'      => 12,
                'is_active'       => true,
            ],
        ];

        foreach ($rules as $rule) {
            AnnualDiscountRule::updateOrCreate(
                ['year' => $rule['year'], 'pay_by_month' => $rule['pay_by_month']],
                $rule
            );
        }
    }
}
