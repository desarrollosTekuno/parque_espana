<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fine extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'members.fines';
     protected $fillable = [
        'act_id',
        'amount',
        'concept',
        'due_date'
    ];

    protected $dates = ['deleted_at'];

    public function act()
    {
        return $this->belongsTo(Act::class);
    }
}
