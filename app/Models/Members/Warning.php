<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warning extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'members.warnings';
    protected $fillable = [
        'act_id',
        'type',
        'has_suspension',
        'suspension_start',
        'suspension_end'
    ];
    protected $dates = ['deleted_at'];

    public function act()
    {
        return $this->belongsTo(Act::class);
    }
}
