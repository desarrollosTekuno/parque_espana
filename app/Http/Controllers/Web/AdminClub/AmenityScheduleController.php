<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\AmenitySchedule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AmenityScheduleController extends Controller {

   /* public function __construct()
    {
        $this->middleware('permission:amenitySchedule.index')->only('index');
        $this->middleware('permission:amenitySchedule.store')->only('store');
        $this->middleware('permission:amenitySchedule.update')->only('update');
        $this->middleware('permission:amenitySchedule.destroy')->only('destroy');
    }*/

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Vista', compact('items'));
    }

    public function store(Request $request)
{
    $schedules = $request->input('schedules', []);

    if (empty($schedules)) {
        return back()->with('messageError', 'No hay horarios para guardar');
    }

    DB::beginTransaction();

    try {
        $resourceId  = $schedules[0]['amenity_id'];
        $newSchedules = [];
        $dayNames = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];
        $conflicts = [];

        foreach ($schedules as $schedule) {
            $dayNumber = $schedule['day_of_week'];
            $newSchedules[$dayNumber] = [
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
            ];
        }

        $reservations = Reservation::selectRaw("
                date,
                start_time,
                end_time,
                EXTRACT(DOW FROM date) as weekday
            ")
            ->where('amenity_id', $resourceId)
            ->whereDate('date', '>=', now()->toDateString())
            ->get();
        
        // Verificar conflictos
       /* foreach ($reservations as $reservation) {
            $weekday = (int)$reservation->weekday;

            if (!isset($newSchedules[$weekday])) continue;

            $schedule = $newSchedules[$weekday];
            $newOpen  = Carbon::parse($schedule['open_time']);
            $newClose = Carbon::parse($schedule['close_time']);
            $resStart = Carbon::parse($reservation->start_time);
            $resEnd   = Carbon::parse($reservation->end_time);

            if ($resStart->lt($newOpen) || $resEnd->gt($newClose)) {
                Log::info('ENTRE AL IFFFF');
                return back()->with('messageError', "Conflicto el día {$weekday}: {$resStart->format('H:i')} - {$resEnd->format('H:i')}");
            }
        }*/

        foreach ($reservations as $reservation) {

            $weekday = (int)$reservation->weekday;

            if (!isset($newSchedules[$weekday])) {
                continue;
            }

            $schedule = $newSchedules[$weekday];

            $newOpen  = Carbon::parse($schedule['open_time']);
            $newClose = Carbon::parse($schedule['close_time']);

            $resStart = Carbon::parse($reservation->start_time);
            $resEnd   = Carbon::parse($reservation->end_time);

            Log::info('DEBUG HORARIOS', [
                'weekday' => $weekday,
                'resStart' => $resStart->format('H:i:s'),
                'newOpen' => $newOpen->format('H:i:s'),
                'resEnd' => $resEnd->format('H:i:s'),
                'newClose' => $newClose->format('H:i:s'),
                'lt_open' => $resStart->lt($newOpen),
                'gt_close' => $resEnd->gt($newClose),
            ]);

            if ($resStart->lt($newOpen) || $resEnd->gt($newClose)) {

                $conflicts[] = [
                    'day' => $dayNames[$weekday],
                    'start' => $resStart->format('H:i'),
                    'end' => $resEnd->format('H:i'),
                ];
            }
        }

        if (!empty($conflicts)) {

            $message = collect($conflicts)
                ->map(fn($c) => "{$c['day']} {$c['start']} - {$c['end']}")
                ->join(', ');

            DB::rollBack();

            return back()->with('messageError', "Existen reservas fuera del nuevo horario: {$message}");
        }
        // Guardar horarios sin conflictos
        AmenitySchedule::where('amenity_id', $resourceId)->forceDelete();
        foreach ($newSchedules as $day => $schedule) {
            AmenitySchedule::create([
                'amenity_id' => $resourceId,
                'day_of_week' => $day,
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'], 
            ]);
        }

        DB::commit();

        return back()->with('success', 'Horarios guardados correctamente');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('ERROR HORARIOS', [
            'exception' => $e->getMessage()
        ]);
        return back()->with('messageError', 'Ocurrió un error al agregar el horario'); 
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
