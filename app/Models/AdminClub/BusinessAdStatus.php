<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessAdStatus extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'advertising.business_ad_statuses';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function ads()
    {
        return $this->hasMany(BusinessAd::class, 'status_id');
    }
}
