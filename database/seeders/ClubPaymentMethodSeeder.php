<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\PaymentMethod;
use Illuminate\Database\Seeder;

class ClubPaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = PaymentMethod::query()->get()->keyBy('code');
        $clubs = Club::query()->get();

        foreach ($clubs as $club) {
            $allowedMethodCodes = [
                'CASH',
                'BANK_TRANSFER_PE1',
                'APP_PAYMENT',
                'BANK_TRANSFER_PE2',
                'CHECK_PE1',
                'CREDIT_CARD_PE1',
                'DEBIT_CARD_PE1',
            ];

            if ($club->code === 'PE2') {
                $allowedMethodCodes[] = 'CHECK_PE2';
                $allowedMethodCodes[] = 'CREDIT_CARD_PE2';
                $allowedMethodCodes[] = 'DEBIT_CARD_PE2';
            }

            foreach ($allowedMethodCodes as $index => $code) {
                $method = $methods->get($code);

                if (!$method) {
                    continue;
                }

                ClubPaymentMethod::updateOrCreate(
                    [
                        'club_id' => $club->id,
                        'payment_method_id' => $method->id,
                    ],
                    [
                        'is_active' => true,
                        'display_order' => $index + 1,
                    ]
                );
            }
        }
    }
}
