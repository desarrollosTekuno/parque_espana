<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenitySchedule extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'amenities.schedules';

    protected $fillable = [
        'amenity_id',
        'day_of_week',
        'open_time',
        'close_time'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function amenity()
    {
        return $this->belongsTo(Amenity::class, 'amenity_id');
    }
}
