<?php

namespace App\Models\Feedback;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Status extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'feedback.statuses';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'status_id');
    }

    public function historiesNew()
    {
        return $this->hasMany(StatusHistory::class, 'new_status_id');
    }

    public function historiesOld()
    {
        return $this->hasMany(StatusHistory::class, 'old_status_id');
    }
}
