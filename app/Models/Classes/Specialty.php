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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
