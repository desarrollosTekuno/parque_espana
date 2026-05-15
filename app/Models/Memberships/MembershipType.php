<?php

namespace App\Models\Memberships;

use App\Models\Administrator\Club;
use App\Models\Catalogs\DocumentType;
use App\Traits\SerializesDates;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipType extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'memberships.types';

    public function pricingRules()
    {
        return $this->hasMany(PricingRule::class, 'membership_type_id');
    }

    public function interclubPackageRulesAsTarget()
    {
        return $this->hasMany(InterclubPackageRule::class, 'target_membership_type_id');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function documentTypes()
    {
        return $this->belongsToMany(
            DocumentType::class,
            'memberships.type_required_documents',
            'membership_type_id',
            'document_type_id'
        )->withPivot([
            'is_required',
            'allow_multiple',
            'number_files',
        ]);
    }
}
