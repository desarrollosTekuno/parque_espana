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
                'pay_by_month'    => 0,
                'discount_months' => 1.0,
                'free_month'      => 12,
                'is_active'       => true,
            ],

            // 2026: enero = mes completo de descuento, febrero = medio mes de descuento
            [
                'pay_by_month'    => 1,
                'discount_months' => 1.0,
                'free_month'      => 12,
                'is_active'       => true,
            ],
            [
                'pay_by_month'    => 2,
                'discount_months' => 0.5,
                'free_month'      => 12,
                'is_active'       => true,
            ],
        ];

        foreach ($rules as $rule) {
            AnnualDiscountRule::updateOrCreate(
                ['pay_by_month' => $rule['pay_by_month']],
                $rule
            );
        }
    }
}
