<?php

namespace App\Models\Notifications;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationAttachment extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'notification_attachments';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}
