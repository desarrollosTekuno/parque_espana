<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SerializesDates;

class InterclubPackageRule extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'memberships.interclub_package_rules';

    protected $casts = [
        'min_years_in_source_club' => 'integer',
        'requires_active_source_membership' => 'boolean',
        'inscription_fee' => 'float',
        'monthly_fee' => 'float',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function sourceClub()
    {
        return $this->belongsTo(Club::class, 'source_club_id');
    }

    public function targetClub()
    {
        return $this->belongsTo(Club::class, 'target_club_id');
    }

    public function sourceMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'source_membership_type_id');
    }

    public function targetMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'target_membership_type_id');
    }
}
