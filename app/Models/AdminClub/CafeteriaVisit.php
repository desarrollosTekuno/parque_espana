<?php

namespace App\Models\AdminClub;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CafeteriaVisit extends Model
{
    use SoftDeletes, SerializesDates;

    protected $table = 'guest_lists.cafeteria_visits';
    protected $connection = 'pgsql';

    protected $fillable = [
        'club_id',
        'visitor_name',
        'document_type',
        'document_number',
        'entered_at',
        'expires_at',
        'min_consumption',
        'consumption_amount',
        'access_fee_waived',
        'access_fee_charged',
        'checked_out_at',
        'document_returned',
        'document_returned_at',
        'status',
        'notes',
        'registered_by',
        'checked_out_by',
    ];

    protected $casts = [
        'entered_at'            => 'datetime',
        'expires_at'            => 'datetime',
        'checked_out_at'        => 'datetime',
        'document_returned_at'  => 'datetime',
        'access_fee_waived'     => 'boolean',
        'document_returned'     => 'boolean',
        'min_consumption'       => 'decimal:2',
        'consumption_amount'    => 'decimal:2',
        'access_fee_charged'    => 'decimal:2',
    ];

    public function isExpired(): bool
    {
        return $this->status === 'active' && now()->isAfter($this->expires_at);
    }
}
