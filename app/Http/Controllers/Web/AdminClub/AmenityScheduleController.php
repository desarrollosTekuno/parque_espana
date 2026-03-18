<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminClub\Reservation;
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
    dd($request->input('schedules'));
    if (empty($schedules)) {
        return back()->with('error', 'No hay horarios para guardar');
    }

    try {

        $daysMap = [
            'monday' => 0,
            'tuesday' => 1,
            'wednesday' => 2,
            'thursday' => 3,
            'friday' => 4,
            'saturday' => 5,
            'sunday' => 6,
        ];

        $amenityId = $schedules[0]['amenity_id'];

        $newSchedules = [];

        foreach ($schedules as $schedule) {
            $dayNumber = $daysMap[$schedule['day_of_week']];

            $newSchedules[$dayNumber] = [
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
            ];
        }

        $reservations = Reservation::selectRaw("
                date,
                start_time,
                end_time,
                WEEKDAY(date) as weekday
            ")
            ->where('amenity_id', $amenityId)
            ->whereDate('date', '>=', now()->toDateString())
            ->get();

        foreach ($reservations as $reservation) {

            $weekday = $reservation->weekday;
            if (!isset($newSchedules[$weekday])) {
                return back()->with(
                    'error',
                    'No puedes eliminar un día que tiene reservaciones futuras.'
                );
            }

            $schedule = $newSchedules[$weekday];

            // Detecta reducción de horario
            if (
                $reservation->start_time < $schedule['open_time'] ||
                $reservation->end_time > $schedule['close_time']
            ) {
                return back()->with(
                    'error',
                    'No puedes reducir el horario porque existen reservaciones fuera del nuevo rango.'
                );
            }
        }

        AmenitySchedule::where('amenity_id', $amenityId)->forceDelete();

        foreach ($newSchedules as $day => $schedule) {

            AmenitySchedule::create([
                'amenity_id' => $amenityId,
                'day_of_week' => $day,
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
            ]);
        }

        return back()->with('success', 'Horarios guardados');

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
