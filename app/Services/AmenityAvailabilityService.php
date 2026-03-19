<?php

namespace App\Services;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Carbon\Carbon;

class AmenityAvailabilityService
{
    public function getSlots(AmenityResource $amenityResource, string $date)
    {
        $date = Carbon::parse($date);
        $dayOfWeek = $date->dayOfWeek;

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

            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $range->open_time);
            $end   = Carbon::parse($date->format('Y-m-d') . ' ' . $range->close_time);

            if ($amenity->reservation_type === 'daily')
            {
                $slotStart = $start;
                $slotEnd = $end;

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
                $reservationsCount = Reservation::where('amenity_resource_id', $amenityResource->id)
                    ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
                    ->where(function ($q) use ($slotStart, $slotEnd) {
                        $q->where('start_datetime', '<', $slotEnd)
                          ->where('end_datetime', '>', $slotStart);
                    })
                    ->count();

                // Determinar cantidad de espacios disponibles
                if ($amenity->reservation_type === 'exclusive')
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

                $slots[] = [
                    'start' => $slotStart->toDateTimeString(),
                    'end' => $slotEnd->toDateTimeString(),
                    'capacity' => $amenityResource->capacity,
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
