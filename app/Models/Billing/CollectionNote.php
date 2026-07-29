<?php

namespace App\Models\Billing;

use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionNote extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $table = 'billing.collection_notes';

    public function membershipAccount()
    {
        return $this->belongsTo(MembershipAccount::class, 'membership_account_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
