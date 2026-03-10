<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:permissions.index')->only('index');
        $this->middleware('permission:permissions.store')->only('store');
        $this->middleware('permission:permissions.update')->only('update');
        $this->middleware('permission:permissions.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        // Detectar el driver de base de datos para adaptar el filtro de búsqueda
        $driver = DB::getDriverName();
        $guards = ['web', 'api'];

        // Prefijo para evitar conflicto con otras tablas
        $prefix = 'permissions';

        // Query base
        $query = Permission::query();

        // Filtro de búsqueda
        if ($search = $request->input("{$prefix}_search")) {
            $query->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('guard_name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('description', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
        }

        // Ordenamiento
        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');
        $query->orderBy($sort, $order);

        // Paginación
        $permissions = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page" // nombre del query param para la página
        )->appends($request->all()); // mantener todos los query params

        return Inertia::render('Administrator/Permissions/Index', [
            // return Inertia::render('Template', [
            'permissions' => $permissions,
            'guards' => $guards,
        ]);
    }

    public function store(Request $request)
    {
        // return $request->all();
        try {
            // validar que el nombre del permiso sea único para el guard especificado
            $validator = Validator::make($request->all(), [
                'name' => 'unique:permissions,name,NULL,id,guard_name,' . $request->guard_name
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors([
                    'messageError' =>  'El nombre del permiso ya existe para el guard seleccionado',
                    'exception' => '',
                ]);
            }

            Permission::create($request->all());
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el permiso',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        // return $request->all();
        try {
            // validar que el nombre del permiso sea único para el guard especificado, excluyendo el permiso actual
            $validator = Validator::make($request->all(), [
                'name' => 'unique:permissions,name,' . $permission->id . ',id,guard_name,' . $request->guard_name
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors([
                    'messageError' =>  'El nombre del permiso ya existe para el guard seleccionado',
                    'exception' => '',
                ]);
            }
            $permission->update($request->all());
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el permiso',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        // dd($permission->roles);
        // return response()->json([
        //     'permission' => $permission
        // ]);
        try {
            // /validar que el permiso no esté asignado a ningún rol antes de eliminarlo
            if ($permission->roles()->count() > 0) {
                return redirect()->back()->withErrors([
                    'messageError' =>  'No se puede eliminar el permiso porque está asignado a
                        uno o más roles',
                    'exception' => '',
                ]);
            }
            $permission->delete();
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el permiso',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
