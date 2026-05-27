<?php

namespace App\Models\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ClubFile extends Model
{
    use HasFactory;

    protected $table = 'files.club_files';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['file_size_formatted', 'file_url'];

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 1) . ' MB';
        if ($bytes >= 1_024) return round($bytes / 1_024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) return null;
        return Storage::disk('spaces')->temporaryUrl($this->file_path, now()->addMinutes(30));
    }
}
