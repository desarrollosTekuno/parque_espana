<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenityResource;
use App\Models\AdminClub\Amenity;
use App\Models\Administrator\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class AmenityController extends Controller {

    public function index() 
    {
        try {

            $amenities = Amenity::with('resources')->where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'message' => 'Amenidades obtenidas correctamente',
                'amenities' => AmenityResource::collection($amenities)
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
                'amenities' => AmenityResource::collection($amenities)
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las amenidades',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
