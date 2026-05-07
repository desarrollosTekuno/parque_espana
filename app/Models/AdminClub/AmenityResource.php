<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdminClub\Amenity;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityResource extends Model
{
    use HasFactory;

    protected $table = 'amenities.resources';

    protected $fillable = [
        'amenity_id',
        'name',
        'capacity',
        'is_active',
        'slot_duration_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'slot_duration_minutes' => 'integer'
    ];

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

}
