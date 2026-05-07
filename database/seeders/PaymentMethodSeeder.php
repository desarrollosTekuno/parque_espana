<?php

namespace Database\Seeders;

use App\Models\Billing\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'code' => 'CASH',
                'name' => 'Efectivo',
                'description' => 'Pago recibido directamente en caja.',
                'requires_reference' => false,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
            ],
            [
                'code' => 'BANK_TRANSFER',
                'name' => 'Transferencia',
                'description' => 'Pago por transferencia bancaria.',
                'requires_reference' => true,
                'requires_bank_name' => true,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
            ],
            [
                'code' => 'APP_PAYMENT',
                'name' => 'Pago en app',
                'description' => 'Pago realizado mediante la aplicacion movil.',
                'requires_reference' => true,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CHECK',
                'name' => 'Cheque',
                'description' => 'Pago mediante cheque bancario.',
                'requires_reference' => false,
                'requires_bank_name' => true,
                'requires_check_number' => true,
                'affects_cash_cut' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SERVICES',
                'name' => 'Pago con servicios',
                'description' => 'Liquidacion de cargos mediante servicios autorizados, sin impacto en corte de caja monetario.',
                'requires_reference' => false,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => false,
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
