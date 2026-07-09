<?php

namespace Database\Seeders;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\Administrator\Club;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\Coach;
use Illuminate\Database\Seeder;

class ClassScheduleSeeder extends Seeder {
    public function run(): void {
        $club = Club::where('code', 'PE2')->first();

        if (!$club) {
            return;
        }

        $tenisPadel = Coach::where('club_id', $club->id)
            ->whereHas('specialties', fn($q) => $q->where('code', 'tennis'))
            ->whereHas('specialties', fn($q) => $q->where('code', 'padel'))
            ->first();

        $tenis = Coach::where('club_id', $club->id)
            ->whereHas('specialties', fn($q) => $q->where('code', 'tennis'))
            ->whereDoesntHave('specialties', fn($q) => $q->where('code', 'padel'))
            ->first();

        $padel = Coach::where('club_id', $club->id)
            ->whereHas('specialties', fn($q) => $q->where('code', 'padel'))
            ->whereDoesntHave('specialties', fn($q) => $q->where('code', 'tennis'))
            ->first();

        $amenityTenis = Amenity::where('club_id', $club->id)
            ->where('name', 'Canchas de tenis')
            ->first();

        $amenityPadel = Amenity::where('club_id', $club->id)
            ->where('name', 'Canchas de pádel')
            ->first();

        if (!$amenityTenis || !$amenityPadel) {
            return;
        }

        $canchasTenis = AmenityResource::where('amenity_id', $amenityTenis->id)->get()->keyBy('name');
        $canchasPadel = AmenityResource::where('amenity_id', $amenityPadel->id)->get()->keyBy('name');

        $schedules = [
            // Tenis adultos - Lunes/Miércoles/Viernes
            [
                'coach'               => $tenis,
                'amenity_resource'    => $canchasTenis->get('Cancha 1'),
                'name'                => 'Tenis adultos - Principiantes',
                'type'                => 'adults',
                'day_of_week'         => 1, // Lunes
                'start_time'          => '08:00:00',
                'end_time'            => '09:00:00',
                'capacity'            => 6,
            ],
            [
                'coach'               => $tenis,
                'amenity_resource'    => $canchasTenis->get('Cancha 1'),
                'name'                => 'Tenis adultos - Principiantes',
                'type'                => 'adults',
                'day_of_week'         => 3, // Miércoles
                'start_time'          => '08:00:00',
                'end_time'            => '09:00:00',
                'capacity'            => 6,
            ],
            // Tenis niños - Martes/Jueves
            [
                'coach'               => $tenis,
                'amenity_resource'    => $canchasTenis->get('Cancha 2'),
                'name'                => 'Tenis niños - Iniciación',
                'type'                => 'kids',
                'day_of_week'         => 2, // Martes
                'start_time'          => '10:00:00',
                'end_time'            => '11:00:00',
                'capacity'            => 8,
            ],
            [
                'coach'               => $tenis,
                'amenity_resource'    => $canchasTenis->get('Cancha 2'),
                'name'                => 'Tenis niños - Iniciación',
                'type'                => 'kids',
                'day_of_week'         => 4, // Jueves
                'start_time'          => '10:00:00',
                'end_time'            => '11:00:00',
                'capacity'            => 8,
            ],
            // Pádel adultos - Lunes/Miércoles
            [
                'coach'               => $padel,
                'amenity_resource'    => $canchasPadel->get('Cancha 1'),
                'name'                => 'Pádel adultos - Nivel básico',
                'type'                => 'adults',
                'day_of_week'         => 1, // Lunes
                'start_time'          => '09:00:00',
                'end_time'            => '10:00:00',
                'capacity'            => 4,
            ],
            [
                'coach'               => $padel,
                'amenity_resource'    => $canchasPadel->get('Cancha 1'),
                'name'                => 'Pádel adultos - Nivel básico',
                'type'                => 'adults',
                'day_of_week'         => 3, // Miércoles
                'start_time'          => '09:00:00',
                'end_time'            => '10:00:00',
                'capacity'            => 4,
            ],
            // Pádel niños - Sábado
            [
                'coach'               => $tenisPadel,
                'amenity_resource'    => $canchasPadel->get('Cancha 2'),
                'name'                => 'Pádel niños - Iniciación',
                'type'                => 'kids',
                'day_of_week'         => 6, // Sábado
                'start_time'          => '09:00:00',
                'end_time'            => '10:00:00',
                'capacity'            => 6,
            ],
        ];

        foreach ($schedules as $data) {
            if (!$data['coach'] || !$data['amenity_resource']) {
                continue;
            }

            ClassSchedule::updateOrCreate(
                [
                    'club_id'             => $club->id,
                    'coach_id'            => $data['coach']->id,
                    'amenity_resource_id' => $data['amenity_resource']->id,
                    'name'                => $data['name'],
                    'day_of_week'         => $data['day_of_week'],
                    'start_time'          => $data['start_time'],
                ],
                [
                    'type'       => $data['type'],
                    'end_time'   => $data['end_time'],
                    'capacity'   => $data['capacity'],
                ]
            );
        }
    }
}
