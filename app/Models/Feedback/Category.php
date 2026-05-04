<?php

namespace App\Models\Feedback;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model {
    use HasFactory, SerializesDates, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'feedback.categories';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tickets() {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
