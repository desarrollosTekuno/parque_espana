<?php

namespace App\Models\Members;

use App\Models\Catalogs\DocumentType;
use App\Models\User;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model
{
    use SerializesDates;

    protected $table = 'members.documents';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
