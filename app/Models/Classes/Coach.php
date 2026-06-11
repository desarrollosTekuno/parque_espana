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
        'name',
        'photo',
        'specialties',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'specialties' => 'array',
    ];

    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
