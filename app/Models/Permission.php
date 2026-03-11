<?php

namespace App\Models;

use App\Traits\SerializesDates;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use SerializesDates;

    public function contexts() {
        return $this->belongsToMany(Context::class, 'permission_has_contexts', 'permission_id', 'context_id');
    }
}
