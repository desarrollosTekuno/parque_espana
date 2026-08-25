<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use App\Models\Classes\Coach;
use App\Models\Classes\Specialty;
use App\Rules\ExistsInSchema;
use App\Rules\NoOverlappingAvailabilities;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class CoachController extends Controller {

    public function __construct() {
        $this->middleware('permission:coaches.index')->only('index');
        $this->middleware('permission:coaches.store')->only('store');
        $this->middleware('permission:coaches.update')->only('update');
        $this->middleware('permission:coaches.destroy')->only('destroy');
    }

    public function index(Request $request) {
        $clubId = $request->club_id ?? session('club_id');

        $coaches = Coach::with(['specialties', 'amenity', 'availabilities'])
            ->where('club_id', $clubId)
            ->when($request->search, fn($q, $s) =>
                $q->whereRaw("CONCAT(first_name, ' ', last_name, ' ', COALESCE(second_last_name, '')) ILIKE ?", ["%{$s}%"])
            )
            ->orderBy('first_name')
            ->paginate($request->per_page ?? 10)
            ->appends($request->all());

        $specialties = Specialty::where('club_id', $clubId)
            ->orderBy('name')
            ->get();

        $amenities = Amenity::where('club_id', $clubId)
            ->orderBy('name')
            ->get();

        return Inertia::render('AdminClubs/Coaches/Index', [
            'coaches' => $coaches,
            'specialties' => $specialties,
            'amenities' => $amenities,
        ]);
    }

    public function store(Request $request) {
        $clubId = session('club_id');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'second_last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'regex:/^\d{10}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'amenity_id' => ['required', new ExistsInSchema('amenities', 'amenities', 'id', ['club_id' => $clubId])],
            'specialties' => ['required', 'array', 'min:1', 'max:2'],
            'specialties.*' => ['integer'],
            'availabilities' => ['nullable', 'array', new NoOverlappingAvailabilities()],
            'availabilities.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'availabilities.*.start_time' => ['required', 'date_format:H:i'],
            'availabilities.*.end_time' => ['required', 'date_format:H:i', 'after:availabilities.*.start_time'],
        ], [
            'phone.regex' => 'El teléfono debe tener exactamente 10 dígitos',
        ]);

        $coach = Coach::create([
            'club_id' => $clubId,
            'amenity_id' => $validated['amenity_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'second_last_name' => $validated['second_last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'photo' => null,
        ]);

        $coach->specialties()->sync($validated['specialties']);
        $coach->availabilities()->createMany($validated['availabilities'] ?? []);

        return redirect()->back()->with('success', 'Entrenador creado correctamente');
    }

    public function update(Request $request, Coach $coach) {
        $clubId = session('club_id');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'second_last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'regex:/^\d{10}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'amenity_id' => ['required', new ExistsInSchema('amenities', 'amenities', 'id', ['club_id' => $clubId])],
            'specialties' => ['required', 'array', 'min:1', 'max:2'],
            'specialties.*' => ['integer'],
            'availabilities' => ['nullable', 'array', new NoOverlappingAvailabilities()],
            'availabilities.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'availabilities.*.start_time' => ['required', 'date_format:H:i'],
            'availabilities.*.end_time' => ['required', 'date_format:H:i', 'after:availabilities.*.start_time'],
        ], [
            'phone.regex' => 'El teléfono debe tener exactamente 10 dígitos',
        ]);

        $coach->update([
            'amenity_id' => $validated['amenity_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'second_last_name' => $validated['second_last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);

        $coach->specialties()->sync($validated['specialties']);

        $coach->availabilities()->delete();
        $coach->availabilities()->createMany($validated['availabilities'] ?? []);

        return redirect()->back()->with('success', 'Entrenador actualizado correctamente');
    }

    public function destroy(Coach $coach) {
        $coach->delete();

        return redirect()->back()->with('success', 'Entrenador eliminado correctamente');
    }
}
