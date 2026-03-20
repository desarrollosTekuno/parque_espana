<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationStatus extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'reservation_status';

    const ACTIVA = 1;
    const CANCELADA = 2;
    const FINALIZADA = 3;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public static function catalogo() {
        return self::orderBy('id')->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'text' => $item->name,
            ];
        });
    }
}
