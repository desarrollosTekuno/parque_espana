<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminClub\AmenitySchedule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AmenityScheduleController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Vista', compact('items'));
    }

    public function store(Request $request)
{
    $schedules = $request->input('schedules', []);

    if (empty($schedules)) {
        return back()->with('error', 'No hay horarios para guardar');
    }

    try {

        $daysMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        $amenityId = $schedules[0]['amenity_id'];

        AmenitySchedule::where('amenity_id', $amenityId)->forceDelete();

        foreach ($schedules as $schedule) {
            AmenitySchedule::create([
                'amenity_id' => $schedule['amenity_id'],
                'day_of_week' => $daysMap[$schedule['day_of_week']],
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
            ]);
        }

        return back()->with('success','Horarios guardados');

    } catch (\Throwable $e) {
        report($e);
        return back()->with('error', $e->getMessage());
    }
}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {
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
    public function destroy(string $id) {
        return redirect()->back()->with('success', 'Message');
    }
}
