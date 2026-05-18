<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\GuestListVariable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class GuestListVariableController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:guest-list-variables.index')->only('index');
        $this->middleware('permission:guest-list-variables.store')->only('store');
        $this->middleware('permission:guest-list-variables.update')->only('update');
        $this->middleware('permission:guest-list-variables.destroy')->only('destroy');
    }

    public function index(Request $request) {

        $clubId = $request->club_id ?? session('club_id');

        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'guestListVariables';

        // Query base
        $query = GuestListVariable::where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {

            $query->where(function ($q) use ($driver, $search) {

                $q->where('code', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orwhere('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('description', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('value', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $guestListVariables = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/GuestListVariables/Index', [
            'guestListVariables' => $guestListVariables,
        ]);
    }

    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique(GuestListVariable::class, 'code')
                        ->where(fn ($query) => $query->where('club_id', session('club_id'))),
                ],
                'name' => 'required|string|max:100',
                'description' => 'required|string|max:255',
                'value' => 'required|string|max:50',
            ]);

            GuestListVariable::create(array_merge($validated, ['club_id' => session('club_id')]));
            return redirect()->back()->with('success', 'Variable creada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear la variable',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, GuestListVariable $guestListVariable)
    {
        try {

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'description' => 'required|string|max:255',
                'value' => 'required|string|max:50',
                Rule::unique(GuestListVariable::class, 'code')
                    ->ignore($guestListVariable->id)
                    ->where(fn ($query) => $query->where('club_id', session('club_id'))),
            ]);

            $guestListVariable->update($validated);
            return redirect()->back()->with('success', 'Variable actualizada correctamente');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la variable',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(GuestListVariable $guestListVariable)
    {
        try {
            $guestListVariable->delete();
            return redirect()->back()->with('success', 'Variable eliminada correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar la variable',
                'exception' => $e->getMessage(),
            ]);
        }

    }
}
