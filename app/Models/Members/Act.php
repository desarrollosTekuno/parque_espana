<?php

namespace App\Models\Members;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Database\Eloquent\SoftDeletes;

class Act extends Model
{   
    protected $table = 'members.acts';
    protected $fillable = [
        'folio',
        'member_id',
        'account_id',
        'club_id',
        'violation_type',
        'description',
        'date',
        'time'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function account()
    {
        return $this->belongsTo(MembershipAccount::class);
    }

    public function fine()
    {
        return $this->hasOne(Fine::class);
    }

    public function warning()
    {
        return $this->hasOne(Warning::class);
    }

    public function files()
    {
        return $this->hasMany(ActFile::class);
    }
}
