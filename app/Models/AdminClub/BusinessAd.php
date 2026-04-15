<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Members\Member;
class BusinessAd extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'advertising.business_ads'; 
    
    protected $fillable = [
        'name',
        'category',
        'description',
        'image',
        'address',
        'phone',
        'email',
        'website',
        'status_id',
        'approved_at',
        'paid_at',
        'published_at',
        'expires_at',
    ];
    protected $dates = ['deleted_at'];

    public function status()
    {
        return $this->belongsTo(BusinessAdStatus::class, 'status_id');
    }
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
