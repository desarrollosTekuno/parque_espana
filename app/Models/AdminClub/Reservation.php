<?php

namespace App\Models\AdminClub;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model {
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
