<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Classes\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::where('code', 'PE1')->first();

        if (!$club) {
            return;
        }

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

        foreach ($specialties as $specialtyData) {
            Specialty::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'code' => $specialtyData['code'],
                ],
                [
                    'name' => $specialtyData['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
