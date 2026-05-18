<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestListRequest;
use App\Models\AdminClub\GuestListVariable;
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

            $member = $request->user()->member;

            $guestList = ReservationGuestList::create([
                'status' => ReservationGuestList::PENDING,
                'total_guests' => $data['total_guests'],
                'total_adults' => $data['total_normal_guests'],
                'total_children' => $data['total_special_guests'],
                'billable_subtotal' => $data['billable_subtotal'],
                'reservation_id' => $validated['reservation_id'] ?? null,
                'club_id' => $validated['club_id'],
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'total_billable_guests' => $data['total_billable_guests'],
                'non_billable_subtotal' => $data['non_billable_subtotal'],
                'member_id' => $member->id
            ]);

            foreach ($validated['guests'] as $guest) {
                ReservationGuestListItem::create([
                    'name' => $guest['name'],
                    'last_name' => $guest['last_name'],
                    'email' => $guest['email'],
                    'phone' => $guest['phone'],
                    'age' => $guest['age'],
                    'guest_list_id' => $guestList->id,
                    'is_billable_to_member' => $guest['is_billable_to_member']
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Lista de invitados creada correctamente'
            ], 201);

        } catch (BusinessRuleException $e){
            return response()->json([
                'success' => false,
                'error' => 'Error de regla',
                'error_details' => $e->getMessage()
            ], 422);
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
        $guests = $data['guests'];

        $normalPrice = GuestListVariable::where('code', 'NORMAL_PRICE')
            ->where('club_id', $data['club_id'])
            ->value('value');

        $specialPrice = GuestListVariable::where('code', 'SPECIAL_PRICE')
            ->where('club_id', $data['club_id'])
            ->value('value');

        if (is_null($normalPrice) || is_null($specialPrice))
        {
            throw new BusinessRuleException('No se han configurado los precios para el club');
        }

        $totalGuests = count($guests);  // total de invitados
        $totalNormalGuests = 0;         // total de invitados normales
        $totalSpecialGuests = 0;        // ttotal de invitados especiales
        $totalBillableGuests = 0;       // total de invitados que pagara el socio
        $totalNonBillableGuests = 0;    // total de invitados que no pagara el socio
        $subtotalBillableGuests = 0;    // subtotal de lo que pagara el socio
        $subtotalNonBillable = 0;       // subtotal de lo que no pagara el socio

        foreach ($guests as $guest) {
            if ($guest['age'] >= 7)
            {
                $totalNormalGuests++;
                if($guest['is_billable_to_member'])
                {
                    $totalBillableGuests++;
                    $subtotalBillableGuests += $normalPrice;
                }
                else {
                    $totalNonBillableGuests++;
                    $subtotalNonBillable += $normalPrice;
                }
            }else{
                $totalSpecialGuests++;
                if($guest['is_billable_to_member'])
                {
                    $totalBillableGuests++;
                    $subtotalBillableGuests += $specialPrice;
                }
                else {
                    $totalNonBillableGuests++;
                    $subtotalNonBillable += $specialPrice;
                }
            }
        }

        return [
            'total_guests' => $totalGuests,
            'total_normal_guests' => $totalNormalGuests,
            'total_special_guests' => $totalSpecialGuests,
            'billable_subtotal' => $subtotalBillableGuests,
            'non_billable_subtotal' => $subtotalNonBillable,
            'total_billable_guests' => $totalBillableGuests
        ];
    }
}
