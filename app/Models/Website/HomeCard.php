<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomeCard extends Model
{
    protected $table = 'website.home_cards';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        return Storage::disk('spaces')->url($this->image_path);
    }
}
