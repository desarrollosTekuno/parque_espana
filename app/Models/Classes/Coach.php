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

    protected $fillable = [
        'club_id',
        'first_name',
        'last_name',
        'second_last_name',
        'phone',
        'email',
        'photo',
        'specialties',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name} {$this->second_last_name}");
    }

    protected $casts = [
        'specialties' => 'array',
    ];

    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }
}
