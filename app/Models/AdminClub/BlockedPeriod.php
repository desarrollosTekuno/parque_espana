<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockedPeriod extends Model {
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'amenities.blocked_periods';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];
}
