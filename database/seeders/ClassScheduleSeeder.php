<?php

namespace Database\Seeders;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\Administrator\Club;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\ClassSession;
use App\Models\Classes\Coach;
use App\Models\Classes\Specialty;
use Illuminate\Database\Seeder;

class ClassScheduleSeeder extends Seeder {
    public function run(): void {
        $club = Club::where('code', 'PE1')->first();

        if (!$club) {
            return;
        }

        $tenis = Coach::where('club_id', $club->id)
            ->whereHas('specialties', fn($q) => $q->where('code', 'tennis'))
            ->first();

        $amenityTenis = Amenity::where('club_id', $club->id)
            ->where('name', 'Canchas de tenis')
            ->first();

        if (!$tenis || !$amenityTenis) {
            return;
        }

        $canchaTenis = AmenityResource::where('amenity_id', $amenityTenis->id)->first();
        $especialidadTenis = Specialty::where('club_id', $club->id)->where('code', 'tennis')->first();

        if (!$canchaTenis) {
            return;
        }

        // Una sola clase, de lunes a viernes, para pruebas.
        for ($dayOfWeek = 1; $dayOfWeek <= 5; $dayOfWeek++) {
            $classSchedule = ClassSchedule::updateOrCreate(
                [
                    'club_id'             => $club->id,
                    'coach_id'            => $tenis->id,
                    'amenity_resource_id' => $canchaTenis->id,
                    'name'                => 'Tenis General',
                    'day_of_week'         => $dayOfWeek,
                    'start_time'          => '08:00:00',
                ],
                [
                    'specialty_id' => $especialidadTenis?->id,
                    'type'         => 'adults',
                    'end_time'     => '09:00:00',
                    'capacity'     => 6,
                ]
            );

            ClassSession::generateForNextDays($classSchedule);
        }
    }
}
