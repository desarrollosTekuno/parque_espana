<?php

namespace App\Http\Controllers\Web\Administrator;

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
        $query = User::query()->with('roles:id,name');

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

        return inertia('Administrator/Users/Index', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {

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
    public function update(Request $request, string $id)
    {
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
    public function destroy(string $id)
    {
        return redirect()->back()->with('success', 'Message');
    }
}
