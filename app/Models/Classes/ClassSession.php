<?php

namespace App\Models\Classes;

use App\Models\AdminClub\AmenityResource;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSession extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.class_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'date'             => 'date',
        'day_of_week'      => 'integer',
        'capacity'         => 'integer',
        'current_capacity' => 'integer',
        'cancelled_at'     => 'datetime',
    ];

    public function classSchedule()
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function amenityResource()
    {
        return $this->belongsTo(AmenityResource::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(ClassEnrollment::class)->whereNull('cancelled_at');
    }
}
