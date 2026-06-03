<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityResourceLocation extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'amenities.locations';
    protected $fillable = [
        'amenity_resource_id',
        'latitude',
        'longitude',
        'active',
    ];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'active' => 'boolean',
    ];

    public function resource()
    {
        return $this->belongsTo(AmenityResource::class, 'amenity_resource_id');
    }
}
