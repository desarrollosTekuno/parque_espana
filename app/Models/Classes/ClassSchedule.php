<?php

namespace App\Models\Classes;

use App\Models\AdminClub\AmenityResource;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSchedule extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.class_schedules';

    protected $guarded = ['id'];

    protected $casts = [
        'capacity'    => 'integer',
        'day_of_week' => 'integer',
        'start_date'  => 'date',
        'end_date'    => 'date',
        'is_active'   => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function amenityResource()
    {
        return $this->belongsTo(AmenityResource::class);
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function sessions()
    {
        return $this->hasMany(ClassSession::class);
    }

}
