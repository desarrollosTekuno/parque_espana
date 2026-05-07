<?php

namespace App\Models\AdminClub;

use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model {
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'reservations.reservations';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];
    protected $casts = [ 'start_datetime' => 'datetime', 'end_datetime' => 'datetime' ];

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function amenityResource()
    {
        return $this->belongsTo(AmenityResource::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function status(){
        return $this->belongsTo(ReservationStatus::class, 'reservation_status_id');
    }

    // Accesor para la fecha
}
