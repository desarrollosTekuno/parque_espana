<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class AnnouncementImage extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'announcements.images';

    protected $fillable = [
        'announcement_id',
        'image',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? Storage::disk('spaces')->url($this->image)
            : null;
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
    
}
