<?php

namespace App\Models\Notifications;

use App\Models\Administrator\Club;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailConfig extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'email_configs';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    public function club()
    {
        return $this->belongsTo(Club::class, 'entity_id');
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'email_config_id');
    }
}
