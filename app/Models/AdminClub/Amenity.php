<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'background_image',
        'reservation_type',
        'capacity',
        'is_active',
        'slot_duration_minutes',
        'club_id',
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
