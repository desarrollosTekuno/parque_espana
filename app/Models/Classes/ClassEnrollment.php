<?php

namespace App\Models\Classes;

use App\Models\Members\Member;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassEnrollment extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'classes.class_enrollments';

    protected $guarded = ['id'];

    protected $casts = [
        'attended_at'  => 'datetime',
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

    public function enrolledByMember()
    {
        return $this->belongsTo(Member::class, 'enrolled_by_member_id');
    }
}
