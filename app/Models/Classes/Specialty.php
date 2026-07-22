<?php

namespace App\Models\Classes;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specialty extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.specialties';

    protected $fillable = [
        'club_id',
        'name',
        'code',
    ];

    public function coaches()
    {
        return $this->belongsToMany(Coach::class, 'classes.coach_specialties', 'specialty_id', 'coach_id');
    }
}
