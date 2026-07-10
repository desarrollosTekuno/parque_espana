<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Classes\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            [
                'name' => 'Tenis',
                'code' => 'tennis',
            ],
            [
                'name' => 'Padel',
                'code' => 'padel',
            ],
        ];

        foreach (['PE1', 'PE2'] as $clubCode) {
            $club = Club::where('code', $clubCode)->first();

            if (!$club) {
                continue;
            }

            foreach ($specialties as $specialtyData) {
                Specialty::updateOrCreate(
                    [
                        'club_id' => $club->id,
                        'code' => $specialtyData['code'],
                    ],
                    [
                        'name' => $specialtyData['name'],
                    ]
                );
            }
        }
    }
}
