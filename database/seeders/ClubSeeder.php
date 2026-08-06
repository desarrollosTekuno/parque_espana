<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $clubs = [
            [
                'name' => 'Parque España I',
                'legal_name' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA',
                'rfc' => 'FDP990423J51',
                'billing_url' => 'http://www.parqueespana2.com.mx',
                'is_active' => true,
                'code' => 'PE1',
                'applies_iva' => false,
            ],
            [
                'name' => 'Parque España II',
                'legal_name' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA',
                'rfc' => 'FDP990423J51',
                'billing_url' => 'http://www.parqueespana2.com.mx',
                'is_active' => true,
                'code' => 'PE2',
                'applies_iva' => true,
            ],
        ];

        foreach ($clubs as $club) {
            Club::updateOrCreate(
                ['code' => $club['code']],
                [
                    'name' => $club['name'],
                    'legal_name' => $club['legal_name'],
                    'rfc' => $club['rfc'],
                    'billing_url' => $club['billing_url'],
                    'is_active' => $club['is_active'],
                    'applies_iva' => $club['applies_iva'],
                ]
            );
        }
    }
}
