<?php

namespace App\Models\Feedback;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'feedback.attachments';

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by_user_id');
    }
}
