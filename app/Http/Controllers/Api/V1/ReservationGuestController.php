<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestListRequest;
use App\Models\AdminClub\GuestListVariable;
use App\Models\AdminClub\ReservationGuestList;
use App\Models\AdminClub\ReservationGuestListItem;
use App\Services\GuestList\Context\GuestListContext;
use App\Services\GuestList\Validators\CreateGuestListValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationGuestController extends Controller
{
    public function store(StoreGuestListRequest $request)
    {
        DB::beginTransaction();
        try {
            $validator = new CreateGuestListValidator();
            $validated = $request->validated();

            $context = new GuestListContext(data: $validated);
            $validator->validate($context);

            $data   = $this->priceCalculator($validated);
            $member = $request->user()->member;

            $guestList = ReservationGuestList::create([
                'status'                => ReservationGuestList::PENDING,
                'total_guests'          => $data['total_guests'],
                'total_adults'          => $data['total_normal_guests'],
                'total_children'        => $data['total_special_guests'],
                'billable_subtotal'     => $data['billable_subtotal'],
                'reservation_id'        => $validated['reservation_id'] ?? null,
                'club_id'               => $validated['club_id'],
                'user_id'               => $request->user()->id,
                'title'                 => $validated['title'],
                'description'           => $validated['description'],
                'date'                  => $validated['date'],
                'time'                  => $validated['time'],
                'total_billable_guests' => $data['total_billable_guests'],
                'non_billable_subtotal' => $data['non_billable_subtotal'],
                'member_id'             => $member->id,
            ]);

            foreach ($data['guests'] as $guest) {
                ReservationGuestListItem::create([
                    'name'                   => $guest['name'],
                    'last_name'              => $guest['last_name'],
                    'email'                  => $guest['email'],
                    'phone'                  => $guest['phone'],
                    'age'                    => $guest['age'],
                    'guest_list_id'          => $guestList->id,
                    'is_billable_to_member'  => $guest['is_billable_to_member'],
                    'price'                  => $guest['price'],
                ]);
            }

            DB::commit();

            return $this->created('Lista de invitados creada correctamente.');
        } catch (BusinessRuleException $e) {
            DB::rollBack();
            return $this->unprocessable($e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return $this->serverError('Ocurrió un error al crear la lista de invitados.');
        }
    }

    public function priceCalculator(array $data): array
    {
        $guests = $data['guests'];

        $normalPrice = GuestListVariable::where('code', 'NORMAL_PRICE')
            ->where('club_id', $data['club_id'])->value('value');

        $specialPrice = GuestListVariable::where('code', 'SPECIAL_PRICE')
            ->where('club_id', $data['club_id'])->value('value');

        if (is_null($normalPrice) || is_null($specialPrice)) {
            throw new BusinessRuleException('No se han configurado los precios para el club');
        }

        $totalGuests            = count($guests);
        $totalNormalGuests      = 0;
        $totalSpecialGuests     = 0;
        $totalBillableGuests    = 0;
        $subtotalBillableGuests = 0;
        $subtotalNonBillable    = 0;

        foreach ($guests as &$guest) {
            if ($guest['age'] >= 7) {
                $guest['price'] = $normalPrice;
                $totalNormalGuests++;
                if ($guest['is_billable_to_member']) {
                    $totalBillableGuests++;
                    $subtotalBillableGuests += $normalPrice;
                } else {
                    $subtotalNonBillable += $normalPrice;
                }
            } else {
                $guest['price'] = $specialPrice;
                $totalSpecialGuests++;
                if ($guest['is_billable_to_member']) {
                    $totalBillableGuests++;
                    $subtotalBillableGuests += $specialPrice;
                } else {
                    $subtotalNonBillable += $specialPrice;
                }
            }
        }

        return [
            'guests'               => $guests,
            'total_guests'         => $totalGuests,
            'total_normal_guests'  => $totalNormalGuests,
            'total_special_guests' => $totalSpecialGuests,
            'billable_subtotal'    => $subtotalBillableGuests,
            'non_billable_subtotal' => $subtotalNonBillable,
            'total_billable_guests' => $totalBillableGuests,
        ];
    }
}
