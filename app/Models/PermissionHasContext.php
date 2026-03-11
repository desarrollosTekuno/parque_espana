<?php

namespace App\Models;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionHasContext extends Model {
    use HasFactory,SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function permission() {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
