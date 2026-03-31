<?php

namespace App\Models\Memberships;

use App\Models\Catalogs\DocumentType;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipTypeRequiredDocument extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'memberships.type_required_documents';

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
}
