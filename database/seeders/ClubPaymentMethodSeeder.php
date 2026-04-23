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
                'BANK_TRANSFER',
                'APP_PAYMENT',
                'SERVICES',
            ];

            if ($club->code === 'PE2') {
                $allowedMethodCodes[] = 'CHECK';
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
