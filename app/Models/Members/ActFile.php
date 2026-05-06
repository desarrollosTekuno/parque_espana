<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActFile extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'members.act_files';
    protected $fillable = [
        'act_id',
        'path'
    ];
    protected $dates = ['deleted_at'];
}
