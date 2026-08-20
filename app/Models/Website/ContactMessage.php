<?php

namespace App\Models\Website;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'website.contact_messages';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
