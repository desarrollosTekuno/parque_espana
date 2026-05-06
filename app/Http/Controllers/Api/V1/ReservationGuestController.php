<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\ReservationGuestList;
use App\Models\AdminClub\ReservationGuestListItem;
use App\Rules\ExistsInSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;


class ReservationGuestController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $validated = $request->validate([
                'reservation_id' => ['required', new ExistsInSchema('reservations', 'reservations', 'id')],
                'guests' => 'required|array|min:1',
                'guests.*.name' => 'required|string|max:200',
                'guests.*.last_name' => 'required|string|max:200',
                'guests.*.email' => 'required|email',
                'guests.*.phone' => 'required|string|max:15|regex:/^\+?[0-9]{8,15}$/',
                'guests.*.age' => 'required|integer',
            ]);

            // calculo de montos para registro general de la lista de invitados
            $adultCost = 300;
            $childCost = 150;

            $guests = $validated['guests'];
            $totalGuests = count($guests);
            $totalAdults = count(array_filter($guests, function($guest) {
                return $guest['age'] > 7;
            }));
            $totalChildren = $totalGuests - $totalAdults;
            $subtotal = $adultCost * $totalAdults + $childCost * $totalChildren;

            $guestList = ReservationGuestList::create([
                'status' => ReservationGuestList::PENDING,
                'total_guests' => $totalGuests,
                'total_adults' => $totalAdults,
                'total_children' => $totalChildren,
                'subtotal' => $subtotal,
                'reservation_id' => $validated['reservation_id'],
                'user_id' => $request->user()->id,
            ]);

            foreach ($guests as $guest) {
                ReservationGuestListItem::create([
                    'name' => $guest['name'],
                    'age' => $guest['age'],
                    'guest_list_id' => $guestList->id
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Lista de invitados creada correctamente'
            ], 200);

        } catch ( ValidationException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'error_details' => $e->errors()
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ocurrió un error al crear la reservación',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
