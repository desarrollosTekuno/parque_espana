<?php

namespace App\Models\Notifications;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailLog extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'email_logs';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    public function club()
    {
        return $this->belongsTo(Club::class, 'entity_id');
    }

    public function emailConfig()
    {
        return $this->belongsTo(EmailConfig::class, 'email_config_id');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}
