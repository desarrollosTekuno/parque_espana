<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\MobileApp\AppVariable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppVariableController extends Controller {

    public function __construct() {
        $this->middleware('permission:app-variables.index')->only('index');
        $this->middleware('permission:app-variables.store')->only('store');
        $this->middleware('permission:app-variables.update')->only('update');
        $this->middleware('permission:app-variables.destroy')->only('destroy');
    }

    public function index(Request $request)
    {

        $clubId = $request->club_id ?? session('club_id');

        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'appVariables';

        // Query base
        $query = AppVariable::where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {

                $q->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('description', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('value', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $appVariables = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/AppVariable/Index', [
            'appVariables' => $appVariables,
        ]);
    }

    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique(AppVariable::class, 'name')
                        ->where(fn ($query) => $query->where('club_id', session('club_id'))),
                ],
                'description' => 'required|string|max:255',
                'value' => 'required|string|max:100',
            ]);

            AppVariable::create(array_merge($validated, ['club_id' => session('club_id')]));
            return redirect()->back()->with('success', 'Variable creada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear la variable',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, AppVariable $appVariable)
    {
        try {

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique(AppVariable::class, 'name')
                        ->ignore($appVariable->id)
                        ->where(fn ($query) => $query->where('club_id', session('club_id'))),
                ],
                'description' => 'required|string|max:255',
                'value' => 'required|string|max:100',
            ]);

            $appVariable->update($validated);
            return redirect()->back()->with('success', 'Variable actualizada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la variable',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(AppVariable $appVariable)
    {
        try {
            $appVariable->delete();
            return redirect()->back()->with('success', 'Variable eliminada correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar la variable',
                'exception' => $e->getMessage(),
            ]);
        }

    }
}
