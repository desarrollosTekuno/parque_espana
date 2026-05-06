<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Model;

class CashCut extends Model
{
    use SerializesDates;

    protected $table = 'billing.cash_cuts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'date' => 'date',
        'opening_amount' => 'float',
        'cash_counted' => 'float',
        'cash_expected' => 'float',
        'cash_difference' => 'float',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function globalCashCut()
    {
        return $this->belongsTo(GlobalCashCut::class, 'global_cash_cut_id');
    }

    public function denominations()
    {
        return $this->hasMany(CashCutDenomination::class, 'cash_cut_id')->orderByDesc('denomination');
    }

    public function payments()
    {
        $tz    = config('app.timezone');
        $start = $this->date->copy()->startOfDay()->setTimezone($tz)->utc();
        $end   = $this->date->copy()->endOfDay()->setTimezone($tz)->utc();

        return Payment::query()
            ->where('club_id', $this->club_id)
            ->where('received_by', $this->user_id)
            ->whereBetween('paid_at', [$start, $end])
            ->whereJsonContains('metadata->settlement_channel', 'cashier');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
