<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\ReservationGuestList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ReservationGuestListController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:guest-lists.index')->only('index');
        $this->middleware('permission:guest-lists.update')->only('update');
    }

    public function index(Request $request)
    {

        $clubId = $request->club_id ?? session('club_id');

        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'guestLists';

        // Query base
        $query = ReservationGuestList::with(['guestListItems', 'reservation', 'reservation.amenityResource'])
            ->whereHas('reservation', function ($q) use ($clubId) {
                $q->where('club_id', $clubId);
            });

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {

                $q->where('status', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                // ->orWhere('description', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                // ->orWhere('value', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $guestLists = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/ReservationGuestLists/Index', [
            'guestLists' => $guestLists,
        ]);
    }

    public function update(Request $request, ReservationGuestList $guestList) {
        try {
            return "hola";

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la información',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
