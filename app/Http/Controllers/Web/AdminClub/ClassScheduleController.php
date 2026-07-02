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

        $sort = $request->sort;
        $order = $request->order === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'type', 'day_of_week', 'capacity'];

        $classSchedules = ClassSchedule::with(['coach', 'amenityResource.amenity'])
            ->where('club_id', $clubId)
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'ILIKE', "%{$s}%")
            )
            ->when($request->coach_id, fn($q, $coachId) =>
                $q->where('coach_id', $coachId)
            )
            ->when($request->amenity_resource_id, fn($q, $amenityResourceId) =>
                $q->where('amenity_resource_id', $amenityResourceId)
            )
            ->when(
                in_array($sort, $allowedSorts, true),
                fn($q) => $q->orderBy($sort, $order),
                fn($q) => $q->orderBy('day_of_week')->orderBy('start_time')
            )
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

    private function detectConflicts(int $coachId, int $amenityResourceId, int $dayOfWeek, string $startTime, string $endTime, ?int $excludeId = null): array {
        $base = ClassSchedule::where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime);
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        $coachConflict = (clone $base)->where('coach_id', $coachId)->first();
        $resourceConflict = (clone $base)->where('amenity_resource_id', $amenityResourceId)->first();

        $errors = [];

        if ($coachConflict) {
            $errors[] = "El entrenador ya tiene la clase \"{$coachConflict->name}\" el {$this->dayLabel($dayOfWeek)} de {$coachConflict->start_time} a {$coachConflict->end_time}.";
        }

        if ($resourceConflict) {
            $errors[] = "La cancha/recurso ya está ocupada por la clase \"{$resourceConflict->name}\" el {$this->dayLabel($dayOfWeek)} de {$resourceConflict->start_time} a {$resourceConflict->end_time}.";
        }

        return $errors;
    }

    private function dayLabel(int $day) {
        return ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$day] ?? "día {$day}";
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'type'                    => 'required|in:adults,kids',
            'coach_id'                => 'required',
            'amenity_resource_id'     => 'required',
            'schedules'               => 'required|array|min:1',
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.start_time'  => 'required|date_format:H:i',
            'schedules.*.end_time'    => 'required|date_format:H:i',
            'schedules.*.capacity'    => 'required|integer|min:1',
        ]);

        $clubId = session('club_id');

        foreach ($validated['schedules'] as $index => $schedule) {
            $conflicts = $this->detectConflicts(
                $validated['coach_id'],
                $validated['amenity_resource_id'],
                $schedule['day_of_week'],
                $schedule['start_time'],
                $schedule['end_time'],
            );

            if (!empty($conflicts)) {
                return back()->withErrors(['schedules.' . $index => implode(' ', $conflicts)])->withInput();
            }
        }

        foreach ($validated['schedules'] as $schedule) {
            ClassSchedule::create([
                'club_id'             => $clubId,
                'coach_id'            => $validated['coach_id'],
                'amenity_resource_id' => $validated['amenity_resource_id'],
                'name'                => $validated['name'],
                'type'                => $validated['type'],
                'day_of_week'         => $schedule['day_of_week'],
                'start_time'          => $schedule['start_time'],
                'end_time'            => $schedule['end_time'],
                'capacity'            => $schedule['capacity'],
            ]);
        }

        return redirect()->back()->with('success', 'Clase(s) creada(s) correctamente');
    }

    public function update(Request $request, ClassSchedule $classSchedule) {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'type'                => 'required|in:adults,kids',
            'coach_id'            => 'required',
            'amenity_resource_id' => 'required',
            'day_of_week'         => 'required|integer|between:0,6',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i',
            'capacity'            => 'required|integer|min:1',
        ]);

        $conflicts = $this->detectConflicts(
            $validated['coach_id'],
            $validated['amenity_resource_id'],
            $validated['day_of_week'],
            $validated['start_time'],
            $validated['end_time'],
            $classSchedule->id,
        );

        if (!empty($conflicts)) {
            return back()->withErrors(['conflict' => implode(' ', $conflicts)])->withInput();
        }

        $classSchedule->update($validated);

        return redirect()->back()->with('success', 'Clase actualizada correctamente');
    }

    public function destroy(ClassSchedule $classSchedule) {
        $classSchedule->delete();

        return redirect()->back()->with('success', 'Clase eliminada correctamente');
    }
}
