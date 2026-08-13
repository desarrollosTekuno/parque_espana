<?php

namespace App\Models\Devices;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Command extends Model {
    use HasFactory;

    protected $table = 'devices.commands';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'data' => 'array',
    ];

    public function device() {
        return $this->belongsTo(Device::class);
    }
}
