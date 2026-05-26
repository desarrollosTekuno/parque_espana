<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ActFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'members.act_files';

    protected $fillable = [
        'act_id',
        'path',
        'mime_type',
    ];

    protected $dates = ['deleted_at'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->path
            ? Storage::disk('spaces')->temporaryUrl($this->path, now()->addMinutes(30))
            : null;
    }
}
