<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Context;
use App\Models\Role;
use App\Models\Web\UserType;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Permission;

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

        $query = Role::with(['permissions:id', 'context:id,name,value'])->orderBy('id', 'desc');

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
        $permissions = Permission::with('contexts')->select('id', 'name', 'description', 'module', 'guard_name')->get();
        $contexts = Context::select('id', 'name', 'value')->get();

        return Inertia::render('Administrator/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'contexts' => $contexts,
            'guards' => $guards,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $guardName  = $request->input('guard_name', 'web');
            $contextId  = $request->input('context_id');

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('roles')->where(fn ($q) => $q
                        ->where('guard_name', $guardName)
                        ->where('context_id', $contextId)
                    ),
                ],
                'description' => ['nullable', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors([
                    'messageError' => 'El nombre del rol ya existe para el guard y contexto seleccionados',
                    'exception'    => '',
                ]);
            }

            $role = Role::create($request->except('permissions'));
            $role->syncPermissions($request->permissions ?? []);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return redirect()->back()->with('success', 'Rol creado con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el rol',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        try {
            $guardName = $request->input('guard_name', $role->guard_name);
            $contextId = $request->input('context_id', $role->context_id);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('roles')->ignore($role->id)->where(fn ($q) => $q
                        ->where('guard_name', $guardName)
                        ->where('context_id', $contextId)
                    ),
                ],
                'description' => ['nullable', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors([
                    'messageError' => 'El nombre del rol ya existe para el guard y contexto seleccionados',
                    'exception'    => '',
                ]);
            }

            $role->update($request->except('permissions'));
            $role->syncPermissions($request->permissions ?? []);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return redirect()->back()->with('success', 'Rol actualizado con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el rol',
                'exception'    => $e->getMessage(),
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
            // validar que el rol no esté asignado a ningún usuario antes de eliminarlo
            if ($role->users()->count() > 0) {
                return redirect()->back()->withErrors([
                    'messageError' =>  'No se puede eliminar el rol porque está asignado a
                        uno o más usuarios',
                    'exception' => '',
                ]);
            }
            $role->delete();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return redirect()->back()->with('success', 'Rol eliminado con éxito!');
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
            $guardName = $request->input('guard_name', 'web');
            $contextId = $request->input('context_id');
            $copyName  = $request->input('name') . ' - Copia';

            // Asegurar que el nombre de la copia no colisione en el mismo contexto
            $exists = Role::where('name', $copyName)
                ->where('guard_name', $guardName)
                ->where('context_id', $contextId)
                ->exists();

            if ($exists) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Ya existe una copia de este rol en el mismo contexto',
                    'exception'    => '',
                ]);
            }

            $role = Role::create(
                collect($request->all())->put('name', $copyName)->toArray()
            );
            $role->syncPermissions($request->permissions ?? []);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            return redirect()->back()->with('success', 'Rol duplicado con éxito!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al duplicar el rol',
                'exception'    => $e->getMessage(),
            ]);
        }
    }
}
