<?php

namespace Database\Seeders;

use App\Models\Billing\ChargeConcept;
use Illuminate\Database\Seeder;

class BillingConceptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $concepts = [
            [
                'code' => 'MONTHLY_FEE',
                'name' => 'Mensualidad',
                'description' => 'Cargo mensual recurrente de la membresia.',
                'default_amount' => null,
                'is_recurring' => true,
                'allows_partial_payments' => false,
                'is_active' => true,
            ],
            [
                'code' => 'INSCRIPTION',
                'name' => 'Inscripción',
                'description' => 'Cargo de inscripción o alta de membresía.',
                'default_amount' => null,
                'is_recurring' => false,
                'allows_partial_payments' => true,
                'is_active' => true,
            ],
            [
                'code' => 'BUSINESS_AD',
                'name' => 'Negocio/Publicidad',
                'description' => 'Cargo por promoción de negocio o publicidad.',
                'default_amount' => 200,
                'is_recurring' => false,
                'allows_partial_payments' => false,
                'is_active' => true,
            ],
            [
                'code' => 'LOCKERS',
                'name' => 'Renta de casilleros',
                'description' => 'Cargo anual por renta de un casillero.',
                'default_amount' => 1100,
                'is_recurring' => false,
                'allows_partial_payments' => false,
                'is_active' => true,
            ],
            [
                'code' => 'GUEST_LIST',
                'name' => 'Lista de invitados',
                'description' => 'Cargo por lista de invitados.',
                'default_amount' => null,
                'is_recurring' => false,
                'allows_partial_payments' => false,
                'is_active' => true,
            ]
        ];

        foreach ($concepts as $concept) {
            ChargeConcept::updateOrCreate(
                ['code' => $concept['code']],
                $concept
            );
        }
    }
}
