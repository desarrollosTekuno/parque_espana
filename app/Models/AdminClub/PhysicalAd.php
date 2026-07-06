<?php

namespace App\Models\AdminClub;

use App\Models\Members\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalAd extends Model
{
    use SoftDeletes;

    protected $table = 'advertising.physical_ads';
    protected $connection = 'pgsql';

    protected $fillable = [
        'club_id',
        'member_id',
        'membership_account_id',
        'physical_ad_size_id',
        'size_label',
        'quantity',
        'amount',
        'starts_at',
        'ends_at',
        'signed_format',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_at'     => 'date',
        'ends_at'       => 'date',
        'signed_format' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function adSize()
    {
        return $this->belongsTo(PhysicalAdSize::class, 'physical_ad_size_id')->withTrashed();
    }
}
