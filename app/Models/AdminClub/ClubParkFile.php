<?php

namespace App\Models\AdminClub;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubParkFile extends Model
{
    use HasFactory;

    protected $table = 'files.club_park_files';

    protected $fillable = [
        'club_id',
        'park_file_id',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function parkFile()
    {
        return $this->belongsTo(ParkFile::class, 'park_file_id');
    }
}
