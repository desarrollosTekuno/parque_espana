<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Locker extends Model
{
    use HasFactory;

    protected $table = 'members.lockers';

    protected $fillable = [
        'club_id',
        'number',
        'category',
        'status',
    ];

    protected $casts = [
        'club_id' => 'integer',
    ];

    public function assignments()
    {
        return $this->hasMany(LockerAssignment::class);
    }

    public function lockerAssignment()
    {
        return $this->hasOne(LockerAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(LockerAssignment::class)
            ->where('year', now()->year);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'disponible');
    }

    public function scopeByClub($query, $clubId)
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}