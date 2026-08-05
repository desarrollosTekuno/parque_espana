<?php

namespace App\Models\Administrator;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\Survey;
use App\Models\Billing\ChargeConceptClubAmount;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\FolioSequence;
use App\Models\Billing\Payment;
use App\Models\Administrator\UserClub;
use App\Models\AdminClub\ClubRule;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Club extends Model {
    use HasFactory, SoftDeletes, SerializesDates;

    protected $table = 'clubs.clubs';
    protected $connection = 'pgsql';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates   = ['deleted_at'];
    protected $appends = ['logo_url', 'mapa_url'];
    protected $casts = [
        'applies_iva' => 'boolean',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }
        return Storage::disk('spaces')->url($this->logo_path);
    }

    public function getMapaUrlAttribute(): ?string
    {
        if (!$this->mapa_path) {
            return null;
        }
        return Storage::disk('spaces')->url($this->mapa_path);
    }

    public function amenities()
    {
        return $this->hasMany(Amenity::class, 'club_id');
    }

    public function clubrules()
    {
        return $this->hasOne(ClubRule::class, 'club_id');
    }

    public function userClub()
    {
        return $this->hasMany(UserClub::class, 'club_id');
    }

    public function clubPaymentMethods()
    {
        return $this->hasMany(ClubPaymentMethod::class, 'club_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'club_id');
    }

    public function clubAddress()
    {
        return $this->hasOne(ClubAddress::class, 'club_id');
    }

    public function folioSequences()
    {
        return $this->hasMany(FolioSequence::class, 'club_id');
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class, 'club_id');
    }

    public function chargeConceptAmounts()
    {
        return $this->hasMany(ChargeConceptClubAmount::class, 'club_id');
    }
}
