<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VirtualTourImage extends Model
{
    protected $table = 'website.virtual_tour_images';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        return Storage::disk('spaces')->url($this->image_path);
    }

    public function category()
    {
        return $this->belongsTo(VirtualTourCategory::class, 'category_id');
    }
}
