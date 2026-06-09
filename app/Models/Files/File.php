<?php

namespace App\Models\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files.files';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    protected $casts = [
        'allowed_mime_types' => 'array',
        'is_required'        => 'boolean',
        'is_active'          => 'boolean',
    ];

    // Listado de módulos
    public static function getModules(){
        return [
            "Administración", "Clubes", "Amenidades", "Reservaciones",
            "Comunicación", "Sistema", "Membresías", "Cobranza",
            "Encuestas", "Publicidad", "App Móvil",
            "Listas de Invitados", "Archivos"
        ];
    }

    public static function getCommonMimeTypes(){
        return [
            [ "title" => "Word (.docx)", "value" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document" ],
            [ "title" => "Word (.doc)", "value" => "application/msword" ],
            [ "title" => "Excel (.xls)", "value" =>  "application/vnd.ms-excel" ],
            [ "title" => "Excel (.xlsx)", "value" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" ]
        ];
    }

    public function clubFiles()
    {
        return $this->hasMany(ClubFile::class, 'file_id');
    }
}
