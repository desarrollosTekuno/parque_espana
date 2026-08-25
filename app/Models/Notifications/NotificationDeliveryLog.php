<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryLog extends Model {
    use HasFactory;

    protected $table = 'notification_delivery_logs';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    public function recipient()
    {
        return $this->belongsTo(NotificationRecipient::class, 'notification_recipient_id');
    }
}
