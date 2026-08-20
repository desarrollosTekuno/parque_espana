<?php

namespace App\Models\Classes;

use Illuminate\Database\Eloquent\Model;

class CoachAvailability extends Model
{
    protected $table = 'classes.coach_availabilities';

    protected $fillable = [
        'coach_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
