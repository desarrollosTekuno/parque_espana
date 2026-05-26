<?php

namespace App\Models\AdminClub;

use App\Models\Administrator\Club;
use App\Models\AdminClub\AnnouncementImage;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model {
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'announcements.announcements';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'club_id',
        'title',
        'content',
        'type',
        'image',
        'is_active',
        'publish_at',
        'expires_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'publish_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function detail()
    {
        return $this->hasOne(AnnouncementDetail::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where(function($q){
            $q->whereNull('publish_at')
              ->orWhere('publish_at', '<=', now());
        });
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function($q){
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>=', now());
        });
    }

    public function images()
    {
        return $this->hasMany(AnnouncementImage::class);
    }

    protected $appends = ['publish_at_formatted', 'image_url'];

    public function getPublishAtFormattedAttribute(): ?string
    {
        return $this->publish_at?->format('Y-m-d H:i:s');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? Storage::disk('spaces')->url($this->image)
            : null;
    }
}
