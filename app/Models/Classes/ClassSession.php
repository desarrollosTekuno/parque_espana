<?php

namespace App\Models\Classes;

use App\Traits\SerializesDates;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory, SerializesDates;

    protected $table = 'classes.class_sessions';

    protected $fillable = [
        'class_schedule_id',
        'date',
        'start_time',
        'end_time',
        'capacity',
        'coach_id',
        'status',
        'cancellation_reason',
    ];

    protected $casts = [
        'date'     => 'date',
        'capacity' => 'integer',
    ];

    public function classSchedule()
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public static function generateForNextDays(ClassSchedule $class, int $days = 7): void
    {
        $date = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            if ($date->dayOfWeek === $class->day_of_week) {
                self::firstOrCreate(
                    ['class_schedule_id' => $class->id, 'date' => $date->toDateString()],
                    [
                        'start_time' => $class->start_time,
                        'end_time'   => $class->end_time,
                        'capacity'   => $class->capacity,
                        'coach_id'   => $class->coach_id,
                        'status'     => 'scheduled',
                    ]
                );
            }

            $date = $date->copy()->addDay();
        }
    }
}
