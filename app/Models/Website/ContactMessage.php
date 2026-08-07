<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'website.contact_messages';

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
