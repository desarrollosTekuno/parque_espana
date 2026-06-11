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

    protected $fillable = [
        'club_id',
        'coach_id',
        'amenity_resource_id',
        'name',
        'type',
        'day_of_week',
        'start_time',
        'end_time',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'capacity'    => 'integer',
        'day_of_week' => 'integer',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
