<?php

namespace App\Models\AdminClub;

use App\Models\Members\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalAd extends Model
{
    use SoftDeletes;

    protected $table = 'advertising.physical_ads';
    protected $connection = 'pgsql';

    protected $fillable = [
        'club_id',
        'member_id',
        'membership_account_id',
        'size',
        'quantity',
        'amount',
        'starts_at',
        'ends_at',
        'signed_format',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_at'     => 'date',
        'ends_at'       => 'date',
        'signed_format' => 'boolean',
    ];

    const SIZES = [
        'carta'        => ['label' => 'Carta',        'price' => 15],
        'oficio'       => ['label' => 'Oficio',       'price' => 20],
        'doble_carta'  => ['label' => 'Doble Carta',  'price' => 30],
        'doble_oficio' => ['label' => 'Doble Oficio', 'price' => 40],
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
