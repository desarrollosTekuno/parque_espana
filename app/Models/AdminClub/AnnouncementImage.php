<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnnouncementImage extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'announcements.images';

    protected $fillable = [
        'announcement_id',
        'image'
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
    
}
