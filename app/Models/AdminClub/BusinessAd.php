<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Members\Member;
class BusinessAd extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'advertising.business_ads'; 
    protected $connection = 'pgsql';
    
    protected $fillable = [
        'member_id',
        'club_id',
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
        'rejection_reason',
    ];
    protected $dates = ['deleted_at'];
    
    protected $casts = [
    'expires_at' => 'datetime',
    'published_at' => 'datetime',
    'paid_at' => 'datetime',
];

    public function status()
    {
        return $this->belongsTo(BusinessAdStatus::class, 'status_id');
    }
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
