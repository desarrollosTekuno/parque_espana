<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LockerAssignment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'members.locker_assignments';

    protected $fillable = [
        'locker_id',
        'member_id',
        'start_date',
        'end_date',
        'amount_paid',
        'year',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount_paid' => 'decimal:2',
        'year' => 'integer',
        'cancellation_reason' => 'string',
    ];

    public function locker()
    {
        return $this->belongsTo(Locker::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function scopeCurrentYear($query)
    {
        return $query->where('year', now()->year);
    }
}