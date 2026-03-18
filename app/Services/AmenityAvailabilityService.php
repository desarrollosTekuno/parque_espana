<?php

namespace App\Services;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Carbon\Carbon;

class AmenityAvailabilityService
{
    public function getSlots(Amenity $amenity, string $date)
    {
        $date = Carbon::parse($date);
        $dayOfWeek = $date->dayOfWeek;

        // Si amenidad está inactiva
        if (!$amenity->is_active) {
            return [];
        }

        $schedules = $amenity->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $slots = [];

        // return $schedules;

        foreach ($schedules as $range) {

            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $range->open_time);
            $end   = Carbon::parse($date->format('Y-m-d') . ' ' . $range->close_time);

            while ($start->copy()->addMinutes($amenity->slot_duration_minutes) <= $end) {

                $slotStart = $start->copy();
                $slotEnd = $start->copy()->addMinutes($amenity->slot_duration_minutes);

                // 🔴 Verificar bloqueos
                // $isBlocked = AmenityBlock::where('amenity_id', $amenity->id)
                //     ->where(function ($q) use ($slotStart, $slotEnd) {
                //         $q->where('start_datetime', '<', $slotEnd)
                //           ->where('end_datetime', '>', $slotStart);
                //     })
                //     ->exists();

                // if ($isBlocked) {
                //     $slots[] = [
                //         'start' => $slotStart->toDateTimeString(),
                //         'end' => $slotEnd->toDateTimeString(),
                //         'capacity' => 0,
                //         'reserved' => 0,
                //         'available_spots' => 0,
                //         'status' => 'blocked'
                //     ];

                //     $start->addMinutes($amenity->slot_duration_minutes);
                //     continue;
                // }

                // 🟢 Contar reservaciones activas
                $reservationsCount = Reservation::where('amenity_id', $amenity->id)
                    ->where('reservation_status_id', ReservationStatus::ACTIVA)
                    ->where(function ($q) use ($slotStart, $slotEnd) {
                        $q->where('start_datetime', '<', $slotEnd)
                          ->where('end_datetime', '>', $slotStart);
                    })
                    ->count();


                if ($amenity->reservation_type === 'exclusive')
                {
                    $availableSpots = 1 - $reservationsCount;
                }else{
                    $availableSpots = $amenity->capacity - $reservationsCount;
                }

                // Determinar estado
                if ($availableSpots <= 0) {
                    $status = 'full';
                } elseif ($reservationsCount > 0) {
                    $status = 'partial';
                } else {
                    $status = 'available';
                }

                $slots[] = [
                    'start' => $slotStart->toDateTimeString(),
                    'end' => $slotEnd->toDateTimeString(),
                    'capacity' => $amenity->capacity,
                    'reserved' => $reservationsCount,
                    'available_spots' => max(0, $availableSpots),
                    'status' => $status
                ];

                $start->addMinutes($amenity->slot_duration_minutes);
            }
        }

        return $slots;
    }
}
