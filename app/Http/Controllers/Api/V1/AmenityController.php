<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenityResource as AmeResource;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\Administrator\Club;
use App\Services\AmenityAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class AmenityController extends Controller {

    protected $amenityAvailabilityService;

    public function __construct(AmenityAvailabilityService $amenityAvailabilityService)
    {
        $this->amenityAvailabilityService = $amenityAvailabilityService;
    }

    public function index()
    {
        try {

            $amenities = Amenity::with('resources')->where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'message' => 'Amenidades obtenidas correctamente',
                'amenities' => AmeResource::collection($amenities)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las amenidades',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function amenitiesByClub(Club $club)
    {
        try {
            $amenities = $club->amenities()->with('resources')->where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'message' => 'Amenidades obtenidas correctamente',
                'amenities' => AmeResource::collection($amenities)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las amenidades',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function availableSlots(Request $request, AmenityResource $amenityResource)
    {
        try {

            $availableSlots = $this->amenityAvailabilityService->getSlots($amenityResource, $request->date);

            return response()->json([
                'success' => true,
                'message' => 'Horarios obtenidos correctamente',
                'available_slots' => $availableSlots
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener los horarios',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
