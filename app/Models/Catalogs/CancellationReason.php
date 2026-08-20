<?php

namespace App\Models\Catalogs;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationReason extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'catalogs.cancellation_reasons';
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
