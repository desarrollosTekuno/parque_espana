<?php

namespace App\Models\Members;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberPaymentSource extends Model
{
    use SoftDeletes;

    protected $table = 'members.payment_sources';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
