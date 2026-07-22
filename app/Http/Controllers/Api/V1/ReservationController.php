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
use App\Support\SpanishDate;
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

            $validated = $request->validate([
                'club_id' => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
                'status_id' => ['nullable', 'string'],
                'amenity_id' => ['nullable', new ExistsInSchema('amenities', 'amenities', 'id')],
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
                'sort' => ['nullable', 'in:asc,desc'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $query = Reservation::with(['amenity', 'amenityResource', 'status', 'club'])
                ->where('member_id', $member->id);

            if (!empty($validated['club_id'])) {
                $query->where('club_id', $validated['club_id']);
            }

            if (!empty($validated['status_id'])) {
                $statusIds = array_filter(explode(',', $validated['status_id']), 'is_numeric');
                if ($statusIds) {
                    $query->whereIn('reservation_status_id', $statusIds);
                }
            }

            if (!empty($validated['amenity_id'])) {
                $query->where('amenity_id', $validated['amenity_id']);
            }

            if (!empty($validated['date_from'])) {
                $query->whereDate('start_datetime', '>=', $validated['date_from']);
            }

            if (!empty($validated['date_to'])) {
                $query->whereDate('start_datetime', '<=', $validated['date_to']);
            }

            $sort = $validated['sort'] ?? 'asc';
            $perPage = $validated['per_page'] ?? 15;

            $paginator = $query->orderBy('start_datetime', $sort)->paginate($perPage);

            $today = Carbon::now()->startOfDay();

            $grouped = collect($paginator->items())
                ->groupBy(fn (Reservation $reservation) => $reservation->start_datetime->format('Y-m-d'))
                ->map(function ($items, $date) use ($today) {
                    $date = Carbon::parse($date);

                    return [
                        'label' => SpanishDate::relativeFullLabel($date, $today),
                        'date' => $date->format('Y-m-d'),
                        'items' => ReservationResource::collection($items)->values(),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Reservaciones obtenidas correctamente',
                'reservations' => $grouped,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'error_details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las reservaciones',
                'error_details' => $e->getMessage()
            ], 500);
        }

    }

}
