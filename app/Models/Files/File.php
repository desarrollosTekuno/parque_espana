<?php

namespace App\Models\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files.files';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'allowed_mime_types' => 'array',
        'is_required'        => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function clubFiles()
    {
        return $this->hasMany(ClubFile::class, 'file_id');
    }
}
