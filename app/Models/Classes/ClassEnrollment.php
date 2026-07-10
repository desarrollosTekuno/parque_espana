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

    protected $fillable = [
        'class_schedule_id',
        'member_id',
        'cancelled_at',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function classSchedule()
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
