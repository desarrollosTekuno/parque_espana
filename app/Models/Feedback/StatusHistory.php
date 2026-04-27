<?php

namespace App\Models\Feedback;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at'];

    protected $table = 'feedback.status_history';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function oldStatus()
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    public function newStatus()
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by_user_id');
    }
}
