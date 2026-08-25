<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckInController extends Controller
{
    private const MAX_DISTANCE_METERS = 5;

    public function store(Request $request, AmenityResource $resource)
    {
        try {
            $request->validate([
                'latitude'  => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'member_id' => 'required|integer',
            ]);

            $memberId = $request->integer('member_id');

            $reservation = Reservation::where('member_id', $memberId)
                ->where('amenity_resource_id', $resource->id)
                ->where('reservation_status_id', ReservationStatus::ACTIVA)
                ->whereDate('start_datetime', today())
                ->first();

            if (!$reservation) {
                return $this->notFound('No se encontró una reservación activa para hoy en este recurso.');
            }

            $activeLocations = $resource->locations()->where('active', true)->get();

            if ($activeLocations->isEmpty()) {
                return $this->unprocessable('Este recurso no tiene ubicaciones activas configuradas.');
            }

            $userLat = (float) $request->input('latitude');
            $userLon = (float) $request->input('longitude');

            $minDistance = $activeLocations->min(function ($location) use ($userLat, $userLon) {
                return $this->haversine($userLat, $userLon, (float) $location->latitude, (float) $location->longitude);
            });

            if ($minDistance > self::MAX_DISTANCE_METERS) {
                return response()->json([
                    'message'  => 'No estás dentro del área del recurso. Acércate e intenta de nuevo.',
                    'distance' => round($minDistance),
                ], 422);
            }

            $reservation->update([
                'reservation_status_id' => ReservationStatus::ASISTENCIA,
            ]);

            $resource->load('amenity');

            return $this->success('¡Asistencia registrada correctamente!', [
                'checked_in_at'  => now()->toIso8601String(),
                'resource'       => $resource->name,
                'amenity'        => $resource->amenity->name,
                'reservation_id' => $reservation->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('CheckIn store error', [
                'resource_id'  => $resource->id,
                'user_id'      => $request->user()?->id,
                'messageError' => $e->getMessage(),
            ]);

            return $this->serverError('No se pudo registrar la asistencia.');
        }
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
