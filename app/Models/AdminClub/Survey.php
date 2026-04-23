<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administrator\Club;

class Survey extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'surveys.surveys';
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'club_id',
        'name',
        'link',
        'is_active',
    ];

      public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
