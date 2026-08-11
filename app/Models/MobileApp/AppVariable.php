<?php

namespace App\Models\MobileApp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppVariable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mobile_app.variables';

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
