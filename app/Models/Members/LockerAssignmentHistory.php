<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LockerAssignmentHistory extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'members.locker_assignment_histories';
    protected $fillable = [
        'locker_assignment_id',
        'member_id',
        'old_locker_id',
        'new_locker_id',
        'changed_at',
        'changed_by',
        'file_path',
    ];
    protected $dates = ['deleted_at'];

    // Relaciones
     public function lockerAssignment()
     {
         return $this->belongsTo(LockerAssignment::class);
     }

     public function member()
     {
         return $this->belongsTo(Member::class);
     }

     public function locker()
     {
         return $this->belongsTo(Locker::class);
     }

    public function oldLocker()
    {
        return $this->belongsTo(Locker::class, 'old_locker_id');
    }

    public function newLocker()
    {
        return $this->belongsTo(Locker::class, 'new_locker_id');
    }

    public function getFileUrlAttribute()
    {
        return $this->file_path
            ? Storage::url($this->file_path)
            : null;
    }
}
