<?php

namespace App\Models\Feedback;


use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'feedback.ticket_types';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'ticket_type_id');
    }
}
