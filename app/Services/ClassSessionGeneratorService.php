<?php

namespace App\Services;

use App\Models\Classes\ClassSchedule;
use App\Models\Classes\ClassSession;
use App\Models\Classes\ClubClosure;
use Carbon\Carbon;

class ClassSessionGeneratorService
{
    private const TIMEZONE = 'America/Mexico_City';
    private const WEEKS_AHEAD = 2;

    /**
     * Genera las sesiones faltantes de todos los horarios activos.
     * Punto de entrada único usado por el cron (classes:generate-sessions);
     * el controlador de clases usa generate() directo para un solo horario
     * recién creado/editado.
     */
    public function generateAll(): int
    {
        $schedules = ClassSchedule::where('is_active', true)->get();

        foreach ($schedules as $schedule) {
            $this->generate($schedule);
        }

        return $schedules->count();
    }

    public function generate(ClassSchedule $classSchedule): void
    {
        if (!$classSchedule->is_active) {
            return;
        }

        $now = Carbon::now(self::TIMEZONE);
        $windowEnd = $now->copy()->startOfDay()->addWeeks(self::WEEKS_AHEAD);

        $from = $now->copy()->startOfDay();
        if ($classSchedule->start_date && $classSchedule->start_date->gt($from)) {
            $from = $classSchedule->start_date->copy();
        }

        $until = $windowEnd;
        if ($classSchedule->end_date && $classSchedule->end_date->lt($until)) {
            $until = $classSchedule->end_date->copy();
        }

        if ($from->gt($until)) {
            return;
        }

        $existingDates = ClassSession::where('class_schedule_id', $classSchedule->id)
            ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $closuresByDate = ClubClosure::where('club_id', $classSchedule->club_id)
            ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->keyBy(fn ($closure) => $closure->date->toDateString());

        $cursor = $from->copy();

        while ($cursor->lte($until)) {
            if ($cursor->dayOfWeek === $classSchedule->day_of_week) {
                $this->generateOccurrence($classSchedule, $cursor, $now, $existingDates, $closuresByDate);
            }

            $cursor->addDay();
        }
    }

    private function generateOccurrence(ClassSchedule $classSchedule, Carbon $date, Carbon $now, array $existingDates, $closuresByDate): void
    {
        $dateString = $date->toDateString();

        if (in_array($dateString, $existingDates, true)) {
            return;
        }

        $occurrenceStart = Carbon::parse("{$dateString} {$classSchedule->start_time}", self::TIMEZONE);

        if ($occurrenceStart->lte($now)) {
            return;
        }

        $closure = $closuresByDate->get($dateString);

        ClassSession::create([
            'date'                => $dateString,
            'day_of_week'         => $classSchedule->day_of_week,
            'start_time'          => $classSchedule->start_time,
            'end_time'            => $classSchedule->end_time,
            'capacity'            => $classSchedule->capacity,
            'current_capacity'    => 0,
            'status'              => $closure ? 'cancelled' : 'scheduled',
            'cancellation_reason' => $closure?->reason,
            'cancelled_at'        => $closure ? $now->copy() : null,
            'club_id'             => $classSchedule->club_id,
            'class_schedule_id'   => $classSchedule->id,
            'coach_id'            => $classSchedule->coach_id,
            'amenity_resource_id' => $classSchedule->amenity_resource_id,
        ]);
    }
}
