<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationGuestList extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'guest_lists.guest_lists';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    protected $appends = ['color'];

    const PENDING = 'PENDIENTE';
    const APPROVED = 'ACEPTADA';
    const REJECTED = 'RECHAZADA';

    public function reservation() {
        return $this->belongsTo(Reservation::class);
    }

    public function guestListItems() {
        return $this->hasMany(ReservationGuestListItem::class, 'guest_list_id');
    }

    // Accessor para color basado en el estado
    public function getColorAttribute() {
        return match ($this->status) {
            self::PENDING => 'gray',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            default => 'gray',
        };
    }
}
