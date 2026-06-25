<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DayPassVisitor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'guest_lists.day_pass_visitors';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'price' => 'float',
        'age'   => 'integer',
    ];

    public function dayPass()
    {
        return $this->belongsTo(DayPass::class, 'day_pass_id');
    }
}
