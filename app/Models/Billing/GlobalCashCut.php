<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Model;

class GlobalCashCut extends Model
{
    use SerializesDates;

    protected $table = 'billing.global_cash_cuts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashCuts()
    {
        return $this->hasMany(CashCut::class, 'global_cash_cut_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
