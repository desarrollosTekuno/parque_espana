<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Models\Members\Member;
use App\Rules\ExistsInSchema;
use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Validators\CancelReservationValidator;
use App\Services\Reservation\Validators\CreateReservationValidator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class ReservationController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request) {

        try {

            $validator = new CreateReservationValidator();

            $validated = $request->validate([
                'start_datetime' => 'required|date_format:Y-m-d H:i',
                'end_datetime' => 'required|date_format:Y-m-d H:i|after:start_datetime',
                //'club_id' =>  ['required', Rule::exists('clubs.clubs as c', 'id')],
                'club_id' =>  ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
                'amenity_resource_id' => ['required', new ExistsInSchema('amenities', 'resources', 'id')] 
            ]);

            $member = Member::where('user_id', $request->user()->id)->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error de validaciÃ³n',
                    'error_details' => 'No se encontrÃ³ un socio asociado a este usuario'
                ], 422);
            }

            $amenityResource = AmenityResource::with('amenity')->where('id', $validated['amenity_resource_id'])->first();
            $amenity = $amenityResource->amenity;

            $context = new ReservationContext(
                data: $validated,
                amenity: $amenity,
                amenityResource: $amenityResource,
                member: $member,
                user: $request->user()
            );
            $validator->validate($context);

            $reservation = Reservation::create([
                'start_datetime' => $validated['start_datetime'],
                'end_datetime' => $validated['end_datetime'],
                'reservation_status_id' => ReservationStatus::ACTIVA,
                'club_id' => $validated['club_id'],
                'amenity_id' => $amenity->id,
                'amenity_resource_id' => $validated['amenity_resource_id'],
                'member_id' => $member->id,
                'reservation_date' => $amenity->reservation_type == 'daily' ? Carbon::parse($validated['start_datetime'])->format('Y-m-d') : null
            ]);

            $reservation->load(['amenity', 'amenityResource', 'status']);

            return response()->json([
                'success' => true,
                'message' => 'Reservación creada correctamente',
                'reservation' => new ReservationResource($reservation)
            ], 201);

        } catch (ReservationException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de regla',
                'error_details' => $e->getMessage()
            ], 422);

        } catch ( ValidationException $e){
            return response()->json([
                'success' => false,
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

    public function update(Request $request, Reservation $reservation)
    {
        try {

            $validator = new CancelReservationValidator();

            $context = new ReservationContext(
                user: $request->user(),
                reservation: $reservation
            );
            $validator->validate($context);

            $reservation->update([
                'cancelled_at' => now(),
                'reservation_status_id' => ReservationStatus::CANCELADA
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reservación cancelada correctamente',
            ], 200);

        } catch (ReservationException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de regla',
                'error_details' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al cancelar la reservación',
                'error_details' => $e->getMessage(),
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
            $member = Member::where('user_id', $request->user()->id)->first();

            if (!$member) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay reservaciones para este usuario',
                    'reservations' => []
                ], 200);
            }

            $reservations = Reservation::with(['amenity', 'amenityResource', 'status'])
                ->where('member_id', $member->id)
                ->orderBy('start_datetime')
                ->get();

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
