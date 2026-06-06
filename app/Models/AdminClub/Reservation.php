<?php

namespace App\Models\AdminClub;

use App\Models\User;
use App\Traits\SerializesDates;
use App\Models\Members\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model {
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'reservations.reservations';

    protected $fillable = [
        'id',
        'start_datetime',
        'end_datetime',
        'cancelled_at',
        'club_id',
        'amenity_id',
        'member_id',
        'amenity_resource_id',
        'reservation_status_id',
        ];
    protected $dates = ['deleted_at'];
    protected $casts = [ 'start_datetime' => 'datetime', 'end_datetime' => 'datetime' ];

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function amenityResource()
    {
        return $this->belongsTo(AmenityResource::class, 'amenity_resource_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function status(){
        return $this->belongsTo(ReservationStatus::class, 'reservation_status_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
