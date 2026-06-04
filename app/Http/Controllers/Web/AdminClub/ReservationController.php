<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\Members\Member;
use App\Models\AdminClub\SystemVariable;
use App\Models\AdminClub\BlockedPeriod;
use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Rules\CancelReservationRule;
use App\Services\Reservation\Rules\UserNoShowPenaltyRule;
use App\Services\Reservation\Rules\ConsecutiveReservationRule;
use App\Exceptions\ReservationException;

class ReservationController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:reservations.index')->only('index');
        $this->middleware('permission:reservations.store')->only('store');
        $this->middleware('permission:reservations.cancel')->only('cancel');
        $this->middleware('permission:reservations.update')->only('update');
    }

    public function index(Request $request)
    {
        $reservationStatus = ReservationStatus::catalogo();

        $clubId = $request->club_id ?? session('club_id');
        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'reservations';

        $filterData = $request->input("{$prefix}_filter_date");
        $filterStatus = $request->input("{$prefix}_filter_status");

        // Query base
        $query = Reservation::with(['amenity', 'amenityResource', 'status'])->where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {
                $q->WhereHas('amenity', function ($q2) use ($driver, $search){
                    $q2->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                })
                ->orWhereHas('amenityResource', function ($q2) use ($driver, $search){
                    $q2->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                });
            });
        }

        if ($filterData) {
            $query->where(function ($q) use ($filterData) {
                $q->whereDate('start_datetime', $filterData)
                ->orWhereDate('end_datetime', $filterData);
            });
                
        }

        if ($filterStatus) {
            $query->where('reservation_status_id', $filterStatus);
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $reservations = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

         $amenities = Amenity::where('club_id', $clubId)
        ->where('is_active', true)
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

        $resources = AmenityResource::where('is_active', true)
            ->select('id', 'name', 'amenity_id')
            ->orderBy('name')
            ->get();

        $members = Member::byClub($clubId)
            ->select('id', 'first_name', 'last_name')
            ->orderBy('first_name')
            ->get();
        
        $diasReserva = SystemVariable::where('club_id', $clubId)
            ->where('name', 'dias_para_crear_reserva')
            ->value('value') ?? 1;

        return Inertia::render('AdminClubs/Reservations/Index', [
            'reservations' => $reservations,
            'activeStatus' => ReservationStatus::ACTIVA,
            'reservationStatus' => $reservationStatus,
            'amenities' => $amenities,
            'resources' => $resources,
            'members' => $members,
            'diasReserva' => (int) $diasReserva,
        ]);

    }

    public function store(Request $request)
    {
        try {
            $clubId = session('club_id');
            $member = Member::find($request->member_id);
            if (!$member) {
                throw new \Exception('El miembro no existe');
            }
            $context = new ReservationContext(
                [
                    'amenity_resource_id' => $request->amenity_resource_id,
                    'start_datetime' => $request->start_datetime,
                    'end_datetime' => $request->end_datetime,
                    'club_id' => $clubId,
                ],
                member: $member
            );
            (new UserNoShowPenaltyRule())->validate($context);
            (new ConsecutiveReservationRule())->validate($context);
            Reservation::create([
                'member_id' => $request->member_id,
                'club_id' => $clubId,
                'amenity_id' => $request->amenity_id,
                'amenity_resource_id' => $request->amenity_resource_id,
                'start_datetime' => $request->start_datetime,
                'end_datetime' => $request->end_datetime,
                'reservation_status_id' => ReservationStatus::ACTIVA
            ]);

            return back()->with('success', 'Reservación creada correctamente');

        } catch (ReservationException $e) {
            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'messageError' => 'Ocurrió un error al crear la reservación: '.$e->getMessage()
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Reservation $reservation) {
        try {
        
            $context = new ReservationContext(
                data: [],
                amenity: null,
                amenityResource: null,
                member: null,
                reservation: $reservation
            );
            (new CancelReservationRule())->validate($context);
            $reservation->update([
                'cancelled_at' => now(),
                'reservation_status_id' => ReservationStatus::CANCELADA
            ]);
            return redirect()->back()->with('success', 'Reservación cancelada con éxito!');
        } catch (ReservationException $e) {
            return redirect()->back()->with('messageError', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {
        return redirect()->back()->with('success', 'Message');
    }

    public function cancel(Reservation $reservation)
    {
        try {
            $context = new ReservationContext(
                data: [],
                amenity: null,
                amenityResource: null,
                member: null,
                reservation: $reservation
            );
            
            (new CancelReservationRule())->validate($context);
            $reservation->update([
                'cancelled_at' => now(),
                'reservation_status_id' => ReservationStatus::CANCELADA
            ]);
            return redirect()->back()->with('success', 'Reservación cancelada con éxito!');
        } catch (ReservationException $e) {
            return redirect()->back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al cancelar la reservación',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function calendar()
    {        
        $club_id = session('club_id');
        $reservations = Reservation::with(['member', 'amenityResource'])
            ->where('club_id', $club_id) 
            ->get();
        $blocked = BlockedPeriod::where('club_id', $club_id)->get();
       
        $statusMap = [
            1 => 'Activa',
            2 => 'Cancelada',
            3 => 'Finalizada',
            4 => 'Inasistencia',
            5 => 'Asistencia',
        ];

        $reservationEvents = $reservations->map(function ($reservation) use ($statusMap) {
            $userName = $reservation->member->full_name ?? 'Usuario';
            $statusId = $reservation->reservation_status_id;
            $amenity = optional($reservation->amenityResource->amenity)->name ?? '';
            $resource = optional($reservation->amenityResource)->name ?? '';    
            return [
                'id' => $reservation->id,
                'title' => "{$userName}",
                'start' => $reservation->start_datetime->format('Y-m-d\TH:i:sP'),
                'end'   => $reservation->end_datetime->format('Y-m-d\TH:i:sP'),
                'status' => $statusMap[$statusId] ?? 'Desconocido',
                'reservation_status_id' => $statusId,
                'amenity_id' => $reservation->amenityResource->amenity_id ?? null,
                'resource_id' => $reservation->amenity_resource_id,
                'amenity_name' => $amenity,
                'resource_name' => $resource,
            ];
        });
        $blockedEvents = $blocked->map(function ($block) {
            $amenity = optional($block->resource->amenity)->name ?? '';
            $resource = optional($block->resource)->name ?? '';

            return [
                'id' => $block->id,
                'title' => $block->reason ?? 'Bloqueo',
                'start' => $block->start_time->format('Y-m-d\TH:i:sP'),
                'end'   => $block->end_time->format('Y-m-d\TH:i:sP'),
                'status' => 'Bloqueado',
                'amenity_id' => optional($block->resource)->amenity_id,
                'resource_id' => $block->resource_id,
                'amenity_name' => $amenity,
                'resource_name' => $resource,
                'calendarId' => 'blocked',
            ];
        });

       return $reservationEvents->concat($blockedEvents)->values();
    }
    
    public function slots(AmenityResource $amenityResource, Request $request)
    {
        $date = $request->input('date');

        $slots = app()->make(\App\Services\AmenityAvailabilityService::class)
            ->getSlots($amenityResource, $date);

        return response()->json($slots);
    }
}
