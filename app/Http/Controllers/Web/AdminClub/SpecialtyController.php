<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Classes\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class SpecialtyController extends Controller {

    public function __construct() {
        $this->middleware('permission:specialties.index')->only('index');
        $this->middleware('permission:specialties.store')->only('store');
        $this->middleware('permission:specialties.update')->only('update');
        $this->middleware('permission:specialties.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $clubId = session('club_id');
            $driver = DB::getDriverName();
            $query = Specialty::where('club_id', $clubId);

            if ($search = trim($request->input('search'))) {
                $operator = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhere('code', $operator, "%{$search}%");
                });
            }

            $specialties = $query
                ->orderBy('name')
                ->paginate($request->input('per_page', 10))
                ->withQueryString();

            return Inertia::render('AdminClubs/Specialties/Index', [
                'specialties' => $specialties,
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/Specialties/Index', [
                'specialties' => [
                    'data' => [],
                    'total' => 0,
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request) {
        $clubId = session('club_id');

        $request->validate([
            'name' => 'required|string|max:120',
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('classes.specialties', 'code')->where(fn ($query) => $query->where('club_id', $clubId)),
            ],
            'is_active' => 'boolean',
        ]);

        try {
            Specialty::create([
                'club_id' => $clubId,
                'name' => $request->name,
                'code' => $request->code,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return back()->with('success', 'Especialidad creada correctamente');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear la especialidad',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id) {
        $clubId = session('club_id');

        $request->validate([
            'name' => 'required|string|max:120',
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('classes.specialties', 'code')
                    ->where(fn ($query) => $query->where('club_id', $clubId))
                    ->ignore($id),
            ],
            'is_active' => 'required|boolean',
        ]);

        try {
            $specialty = Specialty::where('club_id', $clubId)->findOrFail($id);

            $specialty->update([
                'name' => $request->name,
                'code' => $request->code,
                'is_active' => $request->is_active,
            ]);

            return back()->with('success', 'Especialidad actualizada correctamente');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la especialidad',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        try {
            $clubId = session('club_id');
            $specialty = Specialty::where('club_id', $clubId)->findOrFail($id);

            $specialty->delete();

            return back()->with('success', 'Especialidad eliminada correctamente');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar la especialidad',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
