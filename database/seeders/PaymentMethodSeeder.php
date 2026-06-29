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
                'show_in_billing' => true,
            ],
            [
                'code' => 'BANK_TRANSFER_PE1',
                'name' => 'Transferencia',
                'description' => 'Pago por transferencia bancaria para el Parque España 1.',
                'requires_reference' => true,
                'requires_bank_name' => true,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            [
                'code' => 'BANK_TRANSFER_PE2',
                'name' => 'Transferencia',
                'description' => 'Pago por transferencia bancaria para el Parque España 2.',
                'requires_reference' => true,
                'requires_bank_name' => true,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            [
                'code' => 'APP_PAYMENT',
                'name' => 'Pago en app',
                'description' => 'Pago realizado mediante la aplicación móvil.',
                'requires_reference' => true,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => false,
                'provider' => 'conekta',
            ],
            [
                'code' => 'CHECK_PE1',
                'name' => 'Cheque',
                'description' => 'Pago mediante cheque bancario para el Parque España 1.',
                'requires_reference' => false,
                'requires_bank_name' => true,
                'requires_check_number' => true,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            [
                'code' => 'CHECK_PE2',
                'name' => 'Cheque',
                'description' => 'Pago mediante cheque bancario para el Parque España 2.',
                'requires_reference' => false,
                'requires_bank_name' => true,
                'requires_check_number' => true,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            // credit card
            [
                'code' => 'CREDIT_CARD_PE1',
                'name' => 'Tarjeta de crédito',
                'description' => 'Pago realizado con tarjeta de crédito para el Parque España 1.',
                'requires_reference' => true,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            [
                'code' => 'CREDIT_CARD_PE2',
                'name' => 'Tarjeta de crédito',
                'description' => 'Pago realizado con tarjeta de crédito para el Parque España 2.',
                'requires_reference' => true,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            // tarjeta de débito
            [
                'code' => 'DEBIT_CARD_PE1',
                'name' => 'Tarjeta de débito',
                'description' => 'Pago realizado con tarjeta de débito para el Parque España 1.',
                'requires_reference' => true,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            [
                'code' => 'DEBIT_CARD_PE2',
                'name' => 'Tarjeta de débito',
                'description' => 'Pago realizado con tarjeta de débito para el Parque España 2.',
                'requires_reference' => true,
                'requires_bank_name' => false,
                'requires_check_number' => false,
                'affects_cash_cut' => true,
                'is_active' => true,
                'show_in_billing' => true,
            ],
            
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                [
                    'name' => $method['name'],
                    'description' => $method['description'],
                    'requires_reference' => $method['requires_reference'],
                    'requires_bank_name' => $method['requires_bank_name'],
                    'requires_check_number' => $method['requires_check_number'],
                    'affects_cash_cut' => $method['affects_cash_cut'],
                    'is_active' => $method['is_active'],
                    'show_in_billing' => $method['show_in_billing'],
                    'provider' => $method['provider'] ?? null,
                ]
            );
        }
    }
}
