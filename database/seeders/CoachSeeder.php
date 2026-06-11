<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Classes\Coach;
use Illuminate\Database\Seeder;

class CoachSeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::where('code', 'PE1')->first();

        if (!$club) {
            return;
        }

        $coaches = [
            [
                'name'       => 'Carlos Mendoza',
                'specialties' => ['tennis', 'padel'],
                'is_active'  => true,
            ],
            [
                'name'       => 'Ana Gutiérrez',
                'specialties' => ['tennis'],
                'is_active'  => true,
            ],
            [
                'name'       => 'Roberto Sánchez',
                'specialties' => ['padel'],
                'is_active'  => true,
            ],
            [
                'name'       => 'Laura Torres',
                'specialties' => ['tennis', 'padel'],
                'is_active'  => true,
            ],
        ];

        foreach ($coaches as $coachData) {
            Coach::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'name'    => $coachData['name'],
                ],
                [
                    'specialties' => $coachData['specialties'],
                    'is_active'   => $coachData['is_active'],
                ]
            );
        }
    }
}
