<?php

namespace App\Models\AdminClub;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorIncident extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'guest_lists.visitor_incidents';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'charged_amount' => 'float',
    ];

    public function dayPassVisitor()
    {
        return $this->belongsTo(DayPassVisitor::class, 'day_pass_visitor_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
