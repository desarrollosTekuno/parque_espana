<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityResourceLocation extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'amenities.locations';
    protected $fillable = [
        'amenity_resource_id',
        'latitude',
        'longitude',
        'qr_image_path',
        'qr_url',
        'qr_generated_at',
        'qr_generated_by',
        'active',
    ];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'qr_generated_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function resource()
    {
        return $this->belongsTo(AmenityResource::class, 'amenity_resource_id');        
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'qr_generated_by');
    }
}
