<?php

namespace App\Models\Devices;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyPassCard extends Model {
    use HasFactory;

    protected $table = 'devices.daily_pass_cards';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function device() {
        return $this->belongsTo(Device::class);
    }

    public function guestUser() {
        return $this->belongsTo(GuestUser::class);
    }
}
