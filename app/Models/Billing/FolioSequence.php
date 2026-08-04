<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolioSequence extends Model {

    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.folio_sequences';

    protected $casts = [
        'sequence_date' => 'date',
        'last_number' => 'integer',
    ];

    public function club() {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
