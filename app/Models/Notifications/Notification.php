<?php

namespace App\Models\Notifications;

use App\Models\Administrator\Club;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'notifications';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function channel()
    {
        return $this->belongsTo(NotificationChannel::class, 'channel_id');
    }

    public function status()
    {
        return $this->belongsTo(NotificationStatusCatalog::class, 'status_id');
    }

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }

    public function attachments()
    {
        return $this->hasMany(NotificationAttachment::class, 'notification_id');
    }
}
