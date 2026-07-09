<?php

namespace App\Models\Memberships;

use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeparationReason extends Model
{
    use HasFactory, SerializesDates, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'memberships.separation_reasons';

    protected $casts = [
        'requires_document' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function relationship()
    {
        return $this->belongsTo(Relationship::class, 'relationship_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
}
