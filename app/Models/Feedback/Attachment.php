<?php

namespace App\Models\Feedback;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model {
    use HasFactory, SerializesDates, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'feedback.attachments';

    protected $appends = ['storage_path', 'public_url'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by_user_id');
    }

    public function getStoragePathAttribute(): string
    {
        $value = (string) ($this->attributes['file_path'] ?? '');

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '/')) {
            return $value;
        }

        $ticketNumber = $this->ticket?->ticket_number;

        if (!$ticketNumber) {
            return $value;
        }

        return 'Feedback/' . $ticketNumber . '/' . $value;
    }

    public function getPublicUrlAttribute(): string
    {
        $disk = (string) ($this->storage_disk ?: 'public');
        $path = $this->storage_path;

        if ($path === '') {
            return '';
        }

        $url = Storage::disk($disk)->url($path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
