<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\SystemVariable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SystemVariableController extends Controller {

    public function __construct() {
        $this->middleware('permission:system-variables.index')->only('index');
        $this->middleware('permission:system-variables.store')->only('store');
        $this->middleware('permission:system-variables.update')->only('update');
        $this->middleware('permission:system-variables.destroy')->only('destroy');
    }

    public function index(Request $request)
    {

        $clubId = $request->club_id ?? session('club_id');

        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'systemVariables';

        // Query base
        $query = SystemVariable::where('club_id', $clubId);

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

        $systemVariables = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/SystemVariable/Index', [
            'systemVariables' => $systemVariables,
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
                    Rule::unique(SystemVariable::class, 'name')
                        ->where(fn ($query) => $query->where('club_id', session('club_id'))),
                ],
                'description' => 'required|string|max:255',
                'value' => 'required|string|max:50',
            ]);

            SystemVariable::create(array_merge($validated, ['club_id' => session('club_id')]));
            return redirect()->back()->with('success', 'Variable creada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear la variable',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, SystemVariable $systemVariable)
    {
        try {

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'description' => 'required|string|max:255',
                'value' => 'required|string|max:50',
                Rule::unique('system_variables', 'name')
                    ->ignore($systemVariable->id)
                    ->where(fn ($query) => $query->where('club_id', session('club_id'))),
            ]);

            $systemVariable->update($validated);
            return redirect()->back()->with('success', 'Variable actualizada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la variable',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(SystemVariable $systemVariable)
    {
        try {
            $systemVariable->delete();
            return redirect()->back()->with('success', 'Variable eliminada correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar la variable',
                'exception' => $e->getMessage(),
            ]);
        }

    }
}
