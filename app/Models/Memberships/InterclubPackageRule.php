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

    public function feeHistory()
    {
        return $this->hasMany(InterclubPackageRuleFeeHistory::class, 'interclub_package_rule_id');
    }

    /**
     * Ver PricingRule::resolveMonthlyFee() / resolveInscriptionFee() — misma
     * lógica de carry-forward por año.
     */
    public function resolveMonthlyFee(?int $year = null): ?float
    {
        $history = $this->resolveFeeHistory($year);

        return $history ? (float) $history->monthly_fee : null;
    }

    public function resolveInscriptionFee(?int $year = null): ?float
    {
        $history = $this->resolveFeeHistory($year);

        return $history?->inscription_fee !== null ? (float) $history->inscription_fee : null;
    }

    protected function resolveFeeHistory(?int $year = null): ?InterclubPackageRuleFeeHistory
    {
        $year ??= now()->year;

        return $this->feeHistory()
            ->where('year', '<=', $year)
            ->orderByDesc('year')
            ->first();
    }
}
