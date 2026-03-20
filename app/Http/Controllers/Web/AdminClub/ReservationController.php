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

class ReservationController extends Controller {

    public function _construct()
    {
        $this->middleware('permission:reservations.index')->only('index');
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

        // Query base
        $query = Reservation::with(['amenity', 'amenityResource', 'status'])->where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {

            // Filtro de fecha
            $searchDate = trim($search);

            // if(preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search)){
            //     $searchDate = Carbon::createFromFormat('d/m/Y', $search)->format('Y-m-d');

            // } elseif(preg_match('/^\d{2}\/\d{4}$/', $search)){
            //     [$month, $year] = explode('/', $search);
            //     $searchDate = "{$year}-{$month}";

            // } elseif(preg_match('/^\d{2}\/\d{2}$/', $search)) {
            //     [$day, $month] = explode('/', $search);
            //     $searchDate = "{$month}-{$day}";
            // }

            $query->where(function ($q) use ($driver, $search, $searchDate) {
                // $q->where('date', $driver == 'pgsql' ? 'ilike' : 'like', "%{$searchDate}%")
                $q->WhereHas('amenity', function ($q2) use ($driver, $search){
                    $q2->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                })
                ->orWhereHas('amenityResource', function ($q2) use ($driver, $search){
                    $q2->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                })
                ->orWhereHas('status', function ($q2) use ($driver, $search){
                    $q2->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                });
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $reservations = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/Reservations/Index', [
            'reservations' => $reservations,
            'activeStatus' => ReservationStatus::ACTIVA,
            'reservationStatus' => $reservationStatus
        ]);

    }

    public function store(Request $request) {

        //$validated = $request->validate([
        //    'field1' => 'required|string|max:255',
        //    'field2' => 'required|email|unique:table,column',
        //]);

        //Model::create([
        //    'column' => $request->input
        //]);

        return redirect()->back()->with('success', 'Message');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reservation $reservation) {
        try {
            $reservation->update([
                'cancelled_at' => now(),
                'reservation_status_id' => ReservationStatus::CANCELADA
            ]);
            return redirect()->back()->with('success', 'Reservación cancelada con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al cancelar la reservación',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {
        return redirect()->back()->with('success', 'Message');
    }
}
