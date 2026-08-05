<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class VirtualTourCategory extends Model
{
    protected $table = 'website.virtual_tour_categories';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function images()
    {
        return $this->hasMany(VirtualTourImage::class, 'category_id')->orderBy('id');
    }
}
