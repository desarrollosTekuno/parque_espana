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
        $club = Club::where('code', 'PE1')->first();

        if (!$club) {
            return;
        }

        $specialtyCodes = Specialty::where('club_id', $club->id)
            ->pluck('code')
            ->all();

        $coaches = [
            [
                'first_name'       => 'Carlos',
                'last_name'        => 'Mendoza',
                'second_last_name' => 'Ríos',
                'phone'            => '2221000001',
                'email'            => 'carlos.mendoza@parqueespana.mx',
                'specialties'      => $specialtyCodes,
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

        foreach ($coaches as $coachData) {
            Coach::updateOrCreate(
                [
                    'club_id'   => $club->id,
                    'last_name' => $coachData['last_name'],
                    'email'     => $coachData['email'],
                ],
                [
                    'first_name'       => $coachData['first_name'],
                    'second_last_name' => $coachData['second_last_name'],
                    'phone'            => $coachData['phone'],
                    'specialties'      => $coachData['specialties'],
                ]
            );
        }
    }
}
