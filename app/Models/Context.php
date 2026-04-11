<?php

namespace App\Models;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Context extends Model {
    use HasFactory,SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function permissions() {
        return $this->hasMany(PermissionHasContext::class, 'context_id');
    }

    public function roles() {
        return $this->hasMany(Role::class, 'context_id');
    }
}
