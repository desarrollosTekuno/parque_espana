<?php

namespace App\Models\Devices;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'devices.devices';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function club() {
        return $this->belongsTo(Club::class);
    }
}
