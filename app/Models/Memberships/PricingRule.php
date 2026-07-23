<?php

namespace App\Models\Memberships;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model {
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table= 'memberships.pricing_rules';

    protected $casts = [
        'requires_origin_family' => 'boolean',
        'requires_multiple_clubs' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function fromMembershipType()
    {
        return $this->belongsTo(MembershipType::class, 'from_membership_type_id');
    }

    public function feeHistory()
    {
        return $this->hasMany(PricingRuleFeeHistory::class, 'pricing_rule_id');
    }

    /**
     * Cuota mensual vigente en un año dado. Si no hay una captura exacta
     * para ese año, usa la más reciente con año anterior (carry-forward),
     * ya que las cuotas no cambian todos los años. Null si la regla nunca
     * tuvo una cuota capturada (regla recién creada, sin pasar por el
     * módulo de Cuotas por año todavía).
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

    protected function resolveFeeHistory(?int $year = null): ?PricingRuleFeeHistory
    {
        $year ??= now()->year;

        return $this->feeHistory()
            ->where('year', '<=', $year)
            ->orderByDesc('year')
            ->first();
    }
}
