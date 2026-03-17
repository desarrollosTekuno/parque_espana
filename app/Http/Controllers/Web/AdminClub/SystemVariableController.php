<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\SystemVariable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SystemVariableController extends Controller {

    public function _construct() {
        $this->middleware('permission:systemVariables.index')->only('index');
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

    public function MyFunction(Request $request) {
        return "MyFunction";
    }
}
