<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\Reservation;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\Coach;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityResource extends Model
{
    use HasFactory;

    protected $table = 'amenities.resources';

    protected $fillable = [
        'amenity_id',
        'name',
        'capacity',
        'is_active',
        'slot_duration_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'slot_duration_minutes' => 'integer',
    ];

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function locations()
    {
        return $this->hasMany(
            AmenityResourceLocation::class,
            'amenity_resource_id'
        );
    }

    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class, 'amenity_resource_id');
    }

    public function coaches()
    {
        return $this->hasManyThrough(
            Coach::class,
            ClassSchedule::class,
            'amenity_resource_id', // FK en class_schedules
            'id',                  // FK en coaches
            'id',                  // local key en amenity_resources
            'coach_id'              // local key en class_schedules
        );
    }

}
