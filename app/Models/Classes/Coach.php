<?php

namespace App\Models\Classes;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coach extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.coaches';

    protected $guarded = ['id'];

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
}
