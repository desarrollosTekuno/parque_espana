<?php

namespace App\Models\Classes;

use App\Models\Members\Member;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassEnrollment extends Model
{
    use HasFactory, SerializesDates;

    protected $table = 'classes.class_enrollments';

    protected $fillable = [
        'class_session_id',
        'member_id',
        'reserved_by_member_id',
        'cancelled_at',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function reservedByMember()
    {
        return $this->belongsTo(Member::class, 'reserved_by_member_id');
    }
}
