<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessCategory extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'advertising.business_categories';
     protected $fillable = [
        'club_id',
        'name',
        'image',
        'is_active'
    ];
    protected $dates = ['deleted_at'];
}
