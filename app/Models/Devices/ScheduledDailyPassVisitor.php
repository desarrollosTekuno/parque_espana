<?php

namespace App\Models\Devices;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledDailyPassVisitor extends Model {
    use HasFactory;

    protected $table = 'devices.scheduled_daily_pass_visitors';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    public function scheduledDayPass() {
        return $this->belongsTo(ScheduledDailyPass::class, 'scheduled_daily_pass_id');
    }
}
