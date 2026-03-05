<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ClubController extends Controller {

    public function index(Request $request) {
        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'clubs';

        // Query base
        $query = Club::query();

        // Filtro de búsqueda
        if ($search = $request->input("{$prefix}_search")) {
            $query->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('address', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
        }

        // Ordenamiento
        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');
        $query->orderBy($sort, $order);

        // Paginación
        $clubs = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page" // nombre del query param para la página
        )->appends($request->all()); // mantener todos los query params

        return Inertia::render('Administrator/Clubs/Index', [
            // return Inertia::render('Template', [
            'clubs' => $clubs,
        ]);
    }

    public function store(Request $request) {

         try {
            $club = Club::create($request->all());
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al crear el club',
                'exception' => $e->getMessage(),
            ]);
        }
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
