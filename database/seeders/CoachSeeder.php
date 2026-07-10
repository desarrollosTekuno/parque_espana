<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Classes\Coach;
use App\Models\Classes\Specialty;
use Illuminate\Database\Seeder;

class CoachSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = [
            [
                'first_name'       => 'Carlos',
                'last_name'        => 'Mendoza',
                'second_last_name' => 'Ríos',
                'phone'            => '2221000001',
                'email'            => 'carlos.mendoza@parqueespana.mx',
                'specialties'      => ['tennis', 'padel'],
            ],
            [
                'first_name'       => 'Ana',
                'last_name'        => 'Gutiérrez',
                'second_last_name' => 'López',
                'phone'            => '2221000002',
                'email'            => 'ana.gutierrez@parqueespana.mx',
                'specialties'      => ['tennis'],
            ],
            [
                'first_name'       => 'Roberto',
                'last_name'        => 'Sánchez',
                'second_last_name' => 'Vega',
                'phone'            => '2221000003',
                'email'            => 'roberto.sanchez@parqueespana.mx',
                'specialties'      => ['padel'],
            ],
            [
                'first_name'       => 'Laura',
                'last_name'        => 'Torres',
                'second_last_name' => 'Morales',
                'phone'            => '2221000004',
                'email'            => 'laura.torres@parqueespana.mx',
                'specialties'      => ['tennis', 'padel'],
            ],
        ];

        foreach (['PE1', 'PE2'] as $clubCode) {
            $club = Club::where('code', $clubCode)->first();

            if (!$club) {
                continue;
            }

            foreach ($coaches as $coachData) {
                $coach = Coach::updateOrCreate(
                    [
                        'club_id'   => $club->id,
                        'last_name' => $coachData['last_name'],
                        'email'     => $coachData['email'],
                    ],
                    [
                        'first_name'       => $coachData['first_name'],
                        'second_last_name' => $coachData['second_last_name'],
                        'phone'            => $coachData['phone'],
                    ]
                );

                $specialtyIds = Specialty::where('club_id', $club->id)
                    ->whereIn('code', $coachData['specialties'])
                    ->pluck('id');

                $coach->specialties()->sync($specialtyIds);
            }
        }
    }
}
