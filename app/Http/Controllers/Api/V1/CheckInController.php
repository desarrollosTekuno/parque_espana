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
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró una reservación activa para hoy en este recurso.',
                ], 404);
            }

            // Verificar que el usuario esté dentro del radio de al menos una ubicación activa
            $activeLocations = $resource->locations()->where('active', true)->get();

            if ($activeLocations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este recurso no tiene ubicaciones activas configuradas.',
                ], 422);
            }

            $userLat = (float) $request->input('latitude');
            $userLon = (float) $request->input('longitude');

            $minDistance = $activeLocations->min(function ($location) use ($userLat, $userLon) {
                return $this->haversine($userLat, $userLon, (float) $location->latitude, (float) $location->longitude);
            });

            if ($minDistance > self::MAX_DISTANCE_METERS) {
                return response()->json([
                    'success'  => false,
                    'message'  => 'No estás dentro del área del recurso. Acércate e intenta de nuevo.',
                    'distance' => round($minDistance),
                ], 422);
            }

            $reservation->update([
                'reservation_status_id' => ReservationStatus::ASISTENCIA,
            ]);

            $resource->load('amenity');

            return response()->json([
                'success'        => true,
                'message'        => '¡Asistencia registrada correctamente!',
                'checked_in_at'  => now()->toIso8601String(),
                'resource'       => $resource->name,
                'amenity'        => $resource->amenity->name,
                'reservation_id' => $reservation->id,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CheckIn store error', [
                'resource_id'  => $resource->id,
                'user_id'      => $request->user()?->id,
                'messageError' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar la asistencia.',
            ], 500);
        }
    }

    /**
     * Calcula la distancia en metros entre dos coordenadas geográficas
     * usando la fórmula de Haversine.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
