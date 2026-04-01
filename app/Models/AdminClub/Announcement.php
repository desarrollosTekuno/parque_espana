<?php

namespace App\Models\AdminClub;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'announcements.announcements';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'club_id',
        'title',
        'summary',
        'content',
        'type',
        'image',
        'is_featured',
        'is_active',
        'publish_at',
        'expires_at'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
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
}
