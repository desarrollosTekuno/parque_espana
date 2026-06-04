<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockedPeriod extends Model {
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'amenities.blocked_periods';

    protected $fillable = [
        'resource_id',
        'start_time',
        'end_time',
        'reason',
        'is_active',
        'club_id'
    ];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function resource() {
        return $this->belongsTo(AmenityResource::class, 'resource_id');     
    }    
       
}
