<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clubs = [
            [
                'name' => 'Parque España 1',
                'address' => 'Calle 1',
                'is_active' => true,
                'code' => 'PE1',
                'rfc' => '',
                'applies_iva' => false
            ],
            [
                'name' => 'Parque España 2',
                'address' => 'Calle 2',
                'is_active' => true,
                'code' => 'PE2',
                'rfc' => '',
                'applies_iva' => true
            ]
        ];

        foreach ($clubs as $club) {
            Club::updateOrCreate(
                ['code' => $club['code']],
                ['name' => $club['name'], 'address' => $club['address'], 'is_active' => $club['is_active']]
            );
        }
    }
}
