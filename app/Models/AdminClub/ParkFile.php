<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ParkFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files.park_files';

    protected $fillable = [
        'name',
        'description',
        'file_path',
        'file_original_name',
        'file_mime_type',
        'file_size',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['file_url', 'file_size_formatted'];

    public function clubParkFiles()
    {
        return $this->hasMany(ClubParkFile::class, 'park_file_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path
            ? Storage::disk('spaces')->temporaryUrl($this->file_path, now()->addMinutes(30))
            : null;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        if (!$this->file_size) return '—';
        $bytes = $this->file_size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
