<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalAdSize extends Model
{
    use SoftDeletes;

    protected $table = 'advertising.physical_ad_sizes';
    protected $connection = 'pgsql';

    protected $fillable = [
        'club_id',
        'label',
        'price',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForClub($query, int $clubId)
    {
        return $query->where('club_id', $clubId);
    }
}
