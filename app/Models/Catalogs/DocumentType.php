<?php

namespace App\Models\Catalogs;

use App\Traits\SerializesDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model {
    use HasFactory,SerializesDates;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $table = 'catalogs.document_types';
}
