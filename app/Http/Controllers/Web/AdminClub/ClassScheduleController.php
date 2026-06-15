<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\AmenityResource;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\Coach;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class ClassScheduleController extends Controller {

    public function __construct() {
        $this->middleware('permission:classSchedules.index')->only('index');
        $this->middleware('permission:classSchedules.store')->only('store');
        $this->middleware('permission:classSchedules.update')->only('update');
        $this->middleware('permission:classSchedules.destroy')->only('destroy');
    }

    public function index(Request $request) {
        $clubId = $request->club_id ?? session('club_id');

        $classSchedules = ClassSchedule::with(['coach', 'amenityResource'])
            ->where('club_id', $clubId)
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'ILIKE', "%{$s}%")
            )
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($request->per_page ?? 10)
            ->appends($request->all());

        $coaches = Coach::where('club_id', $clubId)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get();

        $amenityResources = AmenityResource::with('amenity')
            ->whereHas('amenity', fn($q) => $q->where('club_id', $clubId))
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('AdminClubs/ClassSchedules/Index', [
            'classSchedules'  => $classSchedules,
            'coaches'         => $coaches,
            'amenityResources' => $amenityResources,
        ]);
    }

    public function store(Request $request) {
        ClassSchedule::create(array_merge(
            $request->all(),
            ['club_id' => session('club_id')]
        ));

        return redirect()->back()->with('success', 'Clase creada correctamente');
    }

    public function update(Request $request, ClassSchedule $classSchedule) {
        $classSchedule->update($request->all());

        return redirect()->back()->with('success', 'Clase actualizada correctamente');
    }

    public function destroy(ClassSchedule $classSchedule) {
        $classSchedule->delete();

        return redirect()->back()->with('success', 'Clase eliminada correctamente');
    }
}
