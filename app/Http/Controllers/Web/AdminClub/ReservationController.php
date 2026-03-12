<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller {

    public function _construct()
    {
        $this->middleware('permission:reservations.index')->only('index');
    }

    public function index(Request $request)
    {
        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'reservations';

        // Query base
        $query = Reservation::with(['amenity']);

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {
                // $q->where('start_time', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                // ->orWhere('end_time', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                $q->where('status', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhereHas('amenity', function ($q2) use ($driver, $search){
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
            'reservations' => $reservations
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
    public function update(Request $request, string $id) {
        //$validated = $request->validate([
        //    'field1' => 'required|string|max:255',
        //    'field2' => 'required|email|unique:table,column,' . $id,
        //]);

        //Model::where('column', $id)->update([
        //    'field1' => $validated['field1'],
        //    'field2' => $validated['field2'],
        //]);

        return redirect()->back()->with('success', 'Message');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {
        return redirect()->back()->with('success', 'Message');
    }
}
