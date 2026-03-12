<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Administrator\Club;
use App\Models\Role;
use Illuminate\Routing\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.index')->only('index');
        $this->middleware('permission:users.store')->only('store');
        $this->middleware('permission:users.update')->only('update');
        $this->middleware('permission:users.destroy')->only('destroy');
    }
    public function index(Request $request)
    {
        // // return Auth::user();
        // $users = User::with(['roles'=>function($query){
        //     $query->select('id','name');
        // }])->paginate(10);
        // //$items = Model::get();
        // return Inertia::render('Administrator/Users/Index', compact('users'));
        //return Inertia::render('Ruta/Vista', compact('items'));
        // $query = User::with(['roles:id,name']);

        // if ($search = $request->get('search')) {
        //     $query->where('name', 'like', "%{$search}%")
        //         ->orWhere('email', 'like', "%{$search}%");
        // }

        // $sort = $request->get('sort', 'id');
        // $order = $request->get('order', 'desc');
        // $query->orderBy($sort, $order);

        // $users = $query->paginate($request->get('per_page', 10))
        //     ->withQueryString();

        // return Inertia::render('Administrator/Users/Index', [
        //     'users' => $users,
        //     'filters' => $request->only(['search', 'sort', 'order', 'per_page']),
        // ]);
        $driver = DB::getDriverName();
        $prefix = 'users';
        $query = User::query()->with('roles:id,name') ->with('clubs:id,name');

        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function ($q) use ($search, $driver) {
                $q->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('email', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        ${$prefix} = $query->orderBy($sort, $order)
            ->paginate($request->input("{$prefix}_per_page", 10), ['*'], "{$prefix}_page")
            ->appends($request->all());
            $roles = Role::select('id', 'name','description')->get();
            $clubs = Club::select('id', 'name')->where('is_active',true)->get();

        return inertia('Administrator/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'clubs' => $clubs,
        ]);
    }

    public function store(Request $request)
    {
        // validar que el email sea único
        $validator = Validator::make($request->all(), [
            'email' => 'unique:users,email'
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors([
                'messageError' =>  'El correo electrónico ya está registrado',
                'exception' => '',
            ]);
        }
        // return $request->all();
        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => 'Pa$$w0rd', // En un caso real, deberías generar una contraseña segura o permitir que el usuario la establezca
            ]);
            $user->roles()->sync($request->roles);
            $user->clubs()->sync($request->clubs);
            // send verification email
            $user->sendEmailVerificationNotification();
            DB::commit();
            return redirect()->back()->with('success', 'Usuario creado con éxito! Se ha enviado un correo de verificación al nuevo usuario.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al crear el usuario',
                'exception' => $e->getMessage(),
            ]);
        }
        //$validated = $request->validate([
        //    'field1' => 'required|string|max:255',
        //    'field2' => 'required|email|unique:table,column',
        //]);

        //Model::create([
        //    'column' => $request->input
        //]);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // validar que el email sea único, excluyendo el usuario actual
        $validator = Validator::make($request->all(), [
            'email' => 'unique:users,email,' . $user->id
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors([
                'messageError' =>  'El correo electrónico ya está registrado',
                'exception' => '',
            ]);
        }
        try {
            DB::beginTransaction();
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);
            $user->roles()->sync($request->roles);
            $user->clubs()->sync($request->clubs);
            // Si el email fue cambiado, enviar un nuevo correo de verificación y revocar la verificación anterior
            if ($user->wasChanged('email')) {
                $user->email_verified_at = null;
                $user->save();
                $user->sendEmailVerificationNotification();
            }
            DB::commit();
            if ($user->wasChanged('email')) {
                return redirect()->back()->with('success', 'Usuario actualizado con éxito! Se ha enviado un nuevo correo de verificación debido al cambio de email.');
            }
             return redirect()->back()->with('success', 'Usuario actualizado con éxito!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al actualizar el usuario',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            DB::beginTransaction();
            $user->roles()->detach();
            $user->clubs()->detach();
            $user->delete();
            DB::commit();
            return redirect()->back()->with('success', 'Usuario eliminado con éxito');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'messageError' =>  'Ocurrió un error al eliminar el usuario',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
