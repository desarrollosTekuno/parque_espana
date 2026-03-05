<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Role;
use App\Models\Web\UserType;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:roles.index')->only('index');
        $this->middleware('permission:roles.store')->only('store');
        $this->middleware('permission:roles.update')->only('update');
        $this->middleware('permission:roles.destroy')->only('destroy');
        $this->middleware('permission:roles.duplicate')->only('duplicate');
    }
    // public function index(Request $request)
    // {
    //     $guards = array('web', 'api');
    //     $roles = Role::with(['permissions' => function ($query) {
    //         $query->select('id');
    //     }, 'userType'])->orderBy('id', 'desc')->paginate(10);
    //     $permissions = Permission::select('id', 'name', 'description', 'context',)->get();
    //     $userTypes = UserType::select('id', 'name', 'description')->get();

    //     return Inertia::render('Administrator/Roles/Index', compact('roles', 'permissions', 'userTypes', 'guards'));
    //     //$items = Model::get();
    //     //return Inertia::render('Ruta/Vista', compact('items'));
    // }

    public function index(Request $request)
    {
        $guards = ['web', 'api'];
        $prefix = 'roles'; // Prefijo para query params de la tabla

        $query = Role::with(['permissions:id'])->orderBy('id', 'desc');

        // Filtro de búsqueda
        if ($search = $request->input("{$prefix}_search")) {
            $driver = DB::getDriverName();
            $query->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('description', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
        }

        // Ordenamiento dinámico
        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');
        $query->orderBy($sort, $order);

        // Paginación con nombre de query param personalizado
        $roles = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        // Datos adicionales
        $permissions = Permission::select('id', 'name', 'description', 'guard_name')->get();

        return Inertia::render('Administrator/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'guards' => $guards,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $role = Role::create($request->except('permissions'));
            $role->syncPermissions($request->permissions);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al crear el rol',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        // return $request->all();
        try {
            $role->update($request->except('permissions'));
            $role->syncPermissions($request->permissions);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al actualizar el rol',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        // return redirect()->back()->with('success', 'Message');
        try {
            $role->delete();
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al eliminar el rol',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function duplicate(Request $request)
    {
        try {
            $roleName = $request->name;
            $roleName = $roleName . '- Copia';
            $role = Role::create(
                collect($request->all())->put('name', $roleName)->toArray()
            );
            $role->syncPermissions($request->permissions);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al crear el rol',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
