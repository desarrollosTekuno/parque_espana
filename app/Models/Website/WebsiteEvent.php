<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class WebsiteEvent extends Model
{
    protected $table = 'website.events';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];
}
