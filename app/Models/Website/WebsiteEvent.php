<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class WebsiteEvent extends Model
{
    protected $table = 'website.events';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
    ];
}
