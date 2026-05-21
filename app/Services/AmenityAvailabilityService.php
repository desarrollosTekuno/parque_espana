<?php

namespace App\Services;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\BlockedPeriod;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AmenityAvailabilityService
{
    public function getSlots(AmenityResource $amenityResource, string $date)
    {
        $tz = 'America/Mexico_City';    
        $date = Carbon::parse($date, $tz);
        $dayOfWeek = $date->dayOfWeek;
        $now = Carbon::now($tz);
        $isToday = $date->isSameDay($now);

        // Si amenidad está inactiva
        if (!$amenityResource->is_active) {
            return [];
        }

        $amenity = $amenityResource->amenity;

        $schedules = $amenity->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $slots = [];

        foreach ($schedules as $range) {

            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $range->open_time, $tz);
            $end   = Carbon::parse($date->format('Y-m-d') . ' ' . $range->close_time, $tz);

            if ($amenity->reservation_type === 'daily')
            {
                $slotStart = $start;
                $slotEnd = $end;

                // 🔴 Verificar bloqueos
                $isBlocked = BlockedPeriod::where('resource_id', $amenityResource->id)
                    ->where(function ($q) use ($slotStart, $slotEnd) {
                        $q->where('start_datetime', '<', $slotEnd)
                          ->where('end_datetime', '>', $slotStart);
                    })
                    ->exists();

                if ($isBlocked) {
                    $slots[] = [
                        'start' => $slotStart->toDateTimeString(),
                        'end' => $slotEnd->toDateTimeString(),
                        'capacity' => 0,
                        'reserved' => 0,
                        'available_spots' => 0,
                        'status' => 'blocked'
                    ];

                    $start->addMinutes($amenityResource->slot_duration_minutes);
                    continue;
                }

                // 🟢 Contar reservaciones activas
                $reservationsCount = Reservation::where('amenity_resource_id', $amenityResource->id)
                    ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
                    ->where(function ($q) use ($slotStart, $slotEnd) {
                        $q->where('start_datetime', '<', $slotEnd)
                          ->where('end_datetime', '>', $slotStart);
                    })
                    ->count();

                $availableSpots = 1 - $reservationsCount;

                $status = $availableSpots <= 0 ? 'full' : ($reservationsCount > 0 ? 'partial' : 'available');

                $slots[] = [
                    'start' => $slotStart->toDateTimeString(),
                    'end' => $slotEnd->toDateTimeString(),
                    'capacity' => $amenityResource->capacity,
                    'reserved' => $reservationsCount,
                    'available_spots' => max(0, $availableSpots),
                    'status' => $status
                ];

                continue;

            }

            while ($start->copy()->addMinutes($amenityResource->slot_duration_minutes) <= $end) {

                $slotStart = $start->copy();
                $slotEnd = $start->copy()->addMinutes($amenityResource->slot_duration_minutes);

                Log::info([
                    'SLOT_START' => $slotStart->toDateTimeString(),
                    'SLOT_TZ' => $slotStart->timezoneName,
                    'NOW' => $now->toDateTimeString(),
                    'NOW_TZ' => $now->timezoneName,
                ]);

                // 🚫 NO mostrar horarios pasados SOLO si es hoy
                /*if ($isToday && $slotStart->copy()->addMinutes($amenityResource->slot_duration_minutes)->lte($now)) {
                    $start->addMinutes($amenityResource->slot_duration_minutes);
                    continue;
                }*/
                if ($isToday && $slotStart->lt($now)) {
                    $start->addMinutes($amenityResource->slot_duration_minutes);
                    continue;
                }

                // 🔴 Verificar bloqueos
                $isBlocked = BlockedPeriod::where('resource_id', $amenityResource->id)
                    ->where(function ($q) use ($slotStart, $slotEnd) {
                        $q->where('start_time', '<', $slotEnd)
                          ->where('end_time', '>', $slotStart);
                    })
                    ->exists();

                if ($isBlocked) {
                    $slots[] = [
                        'start' => $slotStart->toDateTimeString(),
                        'end' => $slotEnd->toDateTimeString(),
                        'capacity' => 0,
                        'reserved' => 0,
                        'available_spots' => 0,
                        'status' => 'blocked'
                    ];

                    $start->addMinutes($amenityResource->slot_duration_minutes);
                    continue;
                }

                // 🟢 Contar reservaciones activas
                $reservationsCount = Reservation::where('amenity_resource_id', $amenityResource->id)
                    ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
                    ->where(function ($q) use ($slotStart, $slotEnd) {
                        $q->where('start_datetime', '<', $slotEnd)
                          ->where('end_datetime', '>', $slotStart);
                    })
                    ->count();

                // Determinar cantidad de espacios disponibles
                if ($amenity->reservation_type === 'hourly')
                {
                    $availableSpots = 1 - $reservationsCount;
                }else{
                    $availableSpots = $amenityResource->capacity - $reservationsCount;
                }

                // Determinar estado
                if ($availableSpots <= 0) {
                    $status = 'full';
                } elseif ($reservationsCount > 0) {
                    $status = 'partial';
                } else {
                    $status = 'available';
                }
Log::info([
    'ADDING_SLOT' => true,
    'SLOT_START' => $slotStart->toDateTimeString(),
    'SLOT_END' => $slotEnd->toDateTimeString(),
]);
                $slots[] = [
                    'start' => $slotStart->toDateTimeString(),
                    'end' => $slotEnd->toDateTimeString(),
                    'capacity' => $amenityResource->capacity,
                    'reserved' => $reservationsCount,
                    'available_spots' => max(0, $availableSpots), 
                    'status' => $status
                ];

                $start->addMinutes($amenityResource->slot_duration_minutes);
            }
        }

        return $slots;
    }
}
