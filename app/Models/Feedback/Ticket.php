<?php

namespace App\Models\Feedback;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'feedback.tickets';

    protected $casts = [
        'is_anonymous' => 'boolean',
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function priority()
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function member()
    {
        return $this->belongsTo(\App\Models\Members\Member::class, 'member_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'reported_by_user_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_user_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'ticket_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'ticket_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class, 'ticket_id');
    }
}
