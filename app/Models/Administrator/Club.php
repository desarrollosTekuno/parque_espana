<?php

namespace App\Models\Administrator;

use App\Models\AdminClub\Amenity;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Administrator\UserClub;
use App\Models\AdminClub\ClubRule;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model {
    use HasFactory, SoftDeletes,SerializesDates;

    protected $table = 'clubs.clubs';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function amenities()
    {
        return $this->hasMany(Amenity::class, 'club_id');
    }

    public function clubrules()
    {
        return $this->hasOne(ClubRule::class, 'club_id');
    }

    public function userClub()
    {
        return $this->hasMany(UserClub::class, 'club_id');
    }

    public function clubPaymentMethods()
    {
        return $this->hasMany(ClubPaymentMethod::class, 'club_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'club_id');
    }
}
