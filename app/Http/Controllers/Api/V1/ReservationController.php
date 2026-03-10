<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class ReservationController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request) {

        try {

            $validated = $request->validate([
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'status' => 'required|string',
                'club_id' =>  'required|exists:clubs,id',
                'amenity_id' => 'required|exists:amenities,id',
                'user_id' => 'required|exists:users,id'
            ]);

            $reservacion = Reservation::create([
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'status' => $validated['status'],
                'club_id' => $validated['club_id'],
                'amenity_id' => $validated['amenity_id'],
                'user_id' => $validated['user_id']
            ]);

            return response()->json([
                'message' => 'Reservación creada correctamente',
                'reservación' => $reservacion
            ], 200);

        } catch ( ValidationException $e){
            return response()->json([
                'error' => 'Error de validación',
                'error_details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al crear la reservación',
                'error_details' => $e->getMessage()
            ], 500);
        }

    }
}
