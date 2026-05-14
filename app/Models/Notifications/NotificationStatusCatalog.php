<?php

namespace App\Models\Notifications;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationStatusCatalog extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'notification_status_catalogs';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'status_id');
    }
}
