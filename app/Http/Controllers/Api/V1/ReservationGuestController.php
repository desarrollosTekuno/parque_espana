<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestListRequest;
use App\Models\AdminClub\ReservationGuestList;
use App\Models\AdminClub\ReservationGuestListItem;
use App\Rules\ExistsInSchema;
use App\Services\GuestList\Context\GuestListContext;
use App\Services\GuestList\Validators\CreateGuestListValidator;
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

    public function store(StoreGuestListRequest $request)
    {
        DB::beginTransaction();
        try {

            $validator = new CreateGuestListValidator();

            $validated = $request->validated();

            $context = new GuestListContext(
                data: $validated
            );
            $validator->validate($context);

            $data = $this->priceCalculator($validated);

            $guestList = ReservationGuestList::create([
                'status' => ReservationGuestList::PENDING,
                'total_guests' => $data['total_guests'],
                'total_adults' => $data['total_normal_guests'],
                'total_children' => $data['total_special_guests'],
                'subtotal' => $data['subtotal'],
                'reservation_id' => $validated['reservation_id'] ?? null,
                'club_id' => $validated['club_id'],
                'user_id' => $request->user()->id,
            ]);

            foreach ($validated['guests'] as $guest) {
                ReservationGuestListItem::create([
                    'name' => $guest['name'],
                    'last_name' => $guest['last_name'],
                    'email' => $guest['email'],
                    'phone' => $guest['phone'],
                    'age' => $guest['age'],
                    'guest_list_id' => $guestList->id
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Lista de invitados creada correctamente'
            ], 200);

        } catch (BusinessRuleException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de regla',
                'error_details' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Ocurrió un error al crear la reservación',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function priceCalculator(array $data)
    {
        $club_id = $data['club_id'];
        $guests = $data['guests'];

        $normalPrice = $club_id == 1 ? 300 : 400; // Invitados de 7 años o más
        $specialPrice = $club_id == 1 ? 150 : 200; // Invitados de 3 a 6 años

        $totalGuests = count($guests);
        $totalNormalGuests = count(array_filter($guests, function($guest) {
            return $guest['age'] >= 7;
        }));
        $totalSpecialGuests = count(array_filter($guests, function($guest) {
            return $guest['age'] >= 3 && $guest['age'] < 7;
        }));

        $subtotal = $normalPrice * $totalNormalGuests + $specialPrice * $totalSpecialGuests;

        return [
            'total_guests' => $totalGuests,
            'total_normal_guests' => $totalNormalGuests,
            'total_special_guests' => $totalSpecialGuests,
            'subtotal' => $subtotal
        ];
    }
}
