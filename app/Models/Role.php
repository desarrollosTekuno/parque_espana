<?php

namespace App\Models;

use App\Models\Web\UserType;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\SerializesDates;

class Role extends SpatieRole
{
    use SerializesDates;
    protected $table = 'roles';

    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }
}
