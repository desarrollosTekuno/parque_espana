<?php

namespace App\Models\Catalogs;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory, SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'catalogs.document_types';
    protected $casts = [
        'is_club_specific' => 'boolean',
    ];

    public function relationships()
    {
        return $this->belongsToMany(Relationship::class, 'catalogs.relationships_document_types', 'document_type_id', 'relationship_id');
    }
}
