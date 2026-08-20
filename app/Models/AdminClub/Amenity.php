<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use App\Models\Administrator\Club;
use App\Models\Classes\Coach;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Amenity extends Model
{
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'amenities.amenities';

    protected $fillable = [
        'name',
        'description',
        'icon',
        'background_image',
        'regulation_file',
        'reservation_type',
        'capacity',
        'is_active',
        'club_id',
    ];
    protected $dates = ['deleted_at'];

    protected $appends = ['icon_url', 'background_image_url', 'regulation_file_url'];

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon
            ? Storage::disk('spaces')->url($this->icon)
            : null;
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image
            ? Storage::disk('spaces')->url($this->background_image)
            : null;
    }

    public function getRegulationFileUrlAttribute(): ?string
    {
        return $this->regulation_file
            ? Storage::disk('spaces')->url($this->regulation_file)
            : null;
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
    public function schedules()
    {
        return $this->hasMany(AmenitySchedule::class, 'amenity_id');
    }

    public function resources()
    {
        return $this->hasMany(AmenityResource::class, 'amenity_id');
    }

    public function coaches()
    {
        return $this->hasMany(Coach::class, 'amenity_id');
    }

}
