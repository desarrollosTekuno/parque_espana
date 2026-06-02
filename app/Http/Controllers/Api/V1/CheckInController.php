<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\AmenityAttendance;
use App\Models\AdminClub\AmenityResourceLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckInController extends Controller
{
    public function store(Request $request, AmenityResourceLocation $location)
    {
        try {
            if (!$location->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta ubicación no está activa.',
                ], 422);
            }

            $location->load('resource.amenity');
            $clubId = $location->resource->amenity->club_id;
            $user   = $request->user();
            $now    = now();

            // Evitar duplicado en el mismo día para el mismo usuario y ubicación
            $alreadyCheckedIn = AmenityAttendance::where('amenity_resource_location_id', $location->id)
                ->where('user_id', $user->id)
                ->whereDate('checked_in_at', $now->toDateString())
                ->exists();

            if ($alreadyCheckedIn) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya registraste tu asistencia hoy en esta ubicación.',
                ], 422);
            }

            $attendance = AmenityAttendance::create([
                'amenity_resource_location_id' => $location->id,
                'user_id'                      => $user->id,
                'club_id'                      => $clubId,
                'checked_in_at'                => $now,
            ]);

            return response()->json([
                'success'        => true,
                'message'        => '¡Asistencia registrada correctamente!',
                'checked_in_at'  => $attendance->checked_in_at->toIso8601String(),
                'resource'       => $location->resource->name,
                'amenity'        => $location->resource->amenity->name,
            ]);

        } catch (\Throwable $e) {
            Log::error('CheckIn store error', [
                'location_id'  => $location->id,
                'user_id'      => $request->user()?->id,
                'messageError' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar la asistencia.',
            ], 500);
        }
    }
}
