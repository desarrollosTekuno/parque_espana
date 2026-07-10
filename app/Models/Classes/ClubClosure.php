<?php

namespace App\Models\Classes;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubClosure extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.club_closures';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
