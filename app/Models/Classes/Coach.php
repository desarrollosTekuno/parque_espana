<?php

namespace App\Models\Classes;

use App\Models\AdminClub\Amenity;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coach extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.coaches';

    protected $fillable = [
        'club_id',
        'amenity_id',
        'first_name',
        'last_name',
        'second_last_name',
        'phone',
        'email',
        'photo',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name} {$this->second_last_name}");
    }

    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function specialties()
    {
        return $this->belongsToMany(Specialty::class, 'classes.coach_specialties', 'coach_id', 'specialty_id');
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function availabilities()
    {
        return $this->hasMany(CoachAvailability::class);
    }
}
