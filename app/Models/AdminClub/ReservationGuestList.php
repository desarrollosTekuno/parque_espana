<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationGuestList extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'reservations.guest_lists';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    const PENDING = 'PENDIENTE';
    const APPROVED = 'ACEPTADA';
    const REJECTED = 'RECHAZADA';

    public function reservation() {
        return $this->belongsTo(Reservation::class);
    }
}
