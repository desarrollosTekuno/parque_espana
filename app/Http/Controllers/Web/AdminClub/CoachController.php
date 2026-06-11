<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Classes\Coach;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class CoachController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:coaches.index')->only('index');
        $this->middleware('permission:coaches.store')->only('store');
        $this->middleware('permission:coaches.update')->only('update');
        $this->middleware('permission:coaches.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');

        $coaches = Coach::where('club_id', $clubId)
            ->when($request->search, fn($q, $s) =>
                $q->whereRaw("CONCAT(first_name, ' ', last_name, ' ', COALESCE(second_last_name, '')) ILIKE ?", ["%{$s}%"])
            )
            ->orderBy('first_name')
            ->paginate($request->per_page ?? 10)
            ->appends($request->all());

        return Inertia::render('AdminClubs/Coaches/Index', [
            'coaches' => $coaches,
        ]);
    }

    public function store(Request $request)
    {
        Coach::create(array_merge(
            $request->all(),
            ['club_id' => session('club_id')]
        ));

        return redirect()->back()->with('success', 'Entrenador creado correctamente');
    }

    public function update(Request $request, Coach $coach)
    {
        $coach->update($request->all());

        return redirect()->back()->with('success', 'Entrenador actualizado correctamente');
    }

    public function destroy(Coach $coach)
    {
        $coach->delete();

        return redirect()->back()->with('success', 'Entrenador eliminado correctamente');
    }
}
