<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockedPeriod extends Model {
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];
}
