<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Services\AmenityAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class ReservationController extends Controller {

    protected $amenityAvailabilityService;

    public function __construct(AmenityAvailabilityService $amenityAvailabilityService)
    {
        $this->amenityAvailabilityService = $amenityAvailabilityService;
    }

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request) {

        try {

            $validated = $request->validate([
                'start_datetime' => 'required|date_format:Y-m-d H:i',
                'end_datetime' => 'required|date_format:Y-m-d H:i|after:start_time',
                'club_id' =>  'required|exists:clubs,id',
                'amenity_id' => 'required|exists:amenities,id'
            ]);

            // Valida que no exista una reservación en el mismo horario
            // $fecha = Carbon::CreateFromFormat('d-m-Y', $validated['date'])->format('Y-m-d');

            // $reservation = Reservation::where('amenity_id', $validated['amenity_id'])
            //     ->where('club_id', $validated['club_id'])
            //     ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
            //     ->where(function ($query) use ($validated){
            //         $query->where('start_datetime', '<', $validated['end_datetime'])
            //               ->where('end_datetime', '>', $validated['start_datetime']);
            //     })
            //     ->first();

            // if ($reservation){
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Ya existe una reservación para la fecha y horario indicados',
            //         'reservación' => $reservation
            //     ], 200);
            // }

            // $reservacion = Reservation::create([
            //     'start_datetime' => $validated['start_datetime'],
            //     'end_datetime' => $validated['end_datetime'],
            //     'reservation_status_id' => ReservationStatus::ACTIVA,
            //     'club_id' => $validated['club_id'],
            //     'amenity_id' => $validated['amenity_id'],
            //     'user_id' => $request->user()->id
            // ]);

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Reservación creada correctamente',
            //     'reservación' => new ReservationResource($reservacion)
            // ], 200);

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

    // public function availableSlots(Request $request, AmenityResource $amenityResource)
    // {
    //     try {

    //         return $amenityResource;

    //         // $availableSlots = $this->amenityAvailabilityService->getSlots($amenity, $request->date);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Horarios obtenidos correctamente',
    //             'available_slots' => $availableSlots
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => 'Ocurrió un error al obtener los horarios',
    //             'error_details' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}
