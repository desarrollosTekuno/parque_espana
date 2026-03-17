<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenitySchedule extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;
    protected $fillable = [
        'amenity_id',
        'day_of_week',
        'open_time',
        'close_time'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];
    public function getDayNameAttribute(){
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ][$this->day_of_week] ?? null;
    }
}
