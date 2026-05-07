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
            ->where('club_id', $clubId);
            // ->whereHas('reservation', function ($q) use ($clubId) {
            //     $q->where('club_id', $clubId);
            // });

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {

                $q->where('status', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('total_guests', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('subtotal', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('discount', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('total', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                // $q->orWhereHas('reservation', function ($q2) use ($driver, $search){
                //     $q2->whereHas('amenityResource', function ($q3) use ($driver, $search){
                //         $q3->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                //     });
                // });
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

            $action = $request->action;

            if ($action == 'approve' )
            {
                $discount = ($guestList->subtotal * $request->discount_percentage) / 100;
                $total = $guestList->subtotal - $discount;
                $status = ReservationGuestList::APPROVED;
                $comments = null;
            }else {
                $discount = null;
                $total = null;
                $status = ReservationGuestList::REJECTED;
                $comments = $request->comments;
            }

            $guestList->update([
                'status' => $status,
                'discount' => $discount,
                'total' => $total,
                'approved_at' => now(),
                'approved_by' => auth()->user()->id,
                'comments' => $comments
            ]);

            return redirect()->back()->with('success', $action == 'approve' ? 'Lista de invitados aprobada correctamente' : 'Lista de invitados rechazada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la información',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
