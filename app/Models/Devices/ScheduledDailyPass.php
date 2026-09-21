<?php

namespace App\Models\Devices;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipAccountMember;

class ScheduledDailyPass extends Model {
    use HasFactory;

    protected $table = 'devices.scheduled_daily_passes';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'visit_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function visitors()
    {
        return $this->hasMany(ScheduledDailyPassVisitor::class, 'scheduled_daily_pass_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function accountMember()
    {
        return $this->belongsTo(MembershipAccountMember::class, 'account_member_id');
    }
}
