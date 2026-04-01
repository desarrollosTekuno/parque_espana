<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnnouncementDetail extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'announcements.details';
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'announcement_id',
        'resource_id',
        'starts_at',
        'ends_at',
        'capacity'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }
    public function resource()
    {
        return $this->belongsTo(AmenityResource::class, 'resource_id');
    }
}
