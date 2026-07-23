<?php

namespace App\Models\Members;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Model;

class ConektaCustomer extends Model
{
    protected $table = 'members.conekta_customers';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
