<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Validators\CreateReservationValidator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class ReservationController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request, CreateReservationValidator $validator) {

        try {

            $validated = $request->validate([
                'start_datetime' => 'required|date_format:Y-m-d H:i',
                'end_datetime' => 'required|date_format:Y-m-d H:i|after:start_datetime',
                'club_id' =>  'required|exists:clubs,id',
                'amenity_resource_id' => 'required|exists:amenity_resources,id'
            ]);

            $amenityResource = AmenityResource::with('amenity')->where('id', $validated['amenity_resource_id'])->first();
            $amenity = $amenityResource->amenity;

            $context = new ReservationContext($validated, $amenity, $amenityResource, $request->user());

            $validator->validate($context);

            $reservacion = Reservation::create([
                'start_datetime' => $validated['start_datetime'],
                'end_datetime' => $validated['end_datetime'],
                'reservation_status_id' => ReservationStatus::ACTIVA,
                'club_id' => $validated['club_id'],
                'amenity_id' => $amenity->id,
                'amenity_resource_id' => $validated['amenity_resource_id'],
                'user_id' => $request->user()->id,
                'reservation_date' => $amenity->reservation_type == 'daily' ? Carbon::parse($validated['start_datetime'])->format('Y-m-d') : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reservación creada correctamente',
                'reservación' => new ReservationResource($reservacion)
            ], 200);

        } catch (ReservationException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de regla',
                'error_details' => $e->getMessage()
            ], 200);

        } catch ( ValidationException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'error_details' => $e->errors()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al crear la reservación',
                'error_details' => $e->getMessage()
            ], 500);
        }

    }

    public function destroy(Reservation $reservation)
    {
        try {
            $reservation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reservación eliminada correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al eliminar la reservación',
                'error_details' => $e->getMessage()
            ], 500);
        }

    }

    public function myReservations(Request $request)
    {
        try {
            $reservations = Reservation::with(['amenity', 'status'])->where('user_id', $request->user()->id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Reservaciones obtenidas correctamente',
                'reservations' => ReservationResource::collection($reservations)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las reservaciones',
                'error_details' => $e->getMessage()
            ], 500);
        }

    }

}
