<?php

namespace App\Models\AdminClub;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AmenityAttendance extends Model
{
    protected $table = 'amenities.attendances';

    protected $fillable = [
        'amenity_resource_location_id',
        'user_id',
        'club_id',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(AmenityResourceLocation::class, 'amenity_resource_location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
