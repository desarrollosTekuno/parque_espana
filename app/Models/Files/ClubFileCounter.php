<?php

namespace App\Models\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ClubFileCounter extends Model {
    use HasFactory;

    protected $table = 'files.club_file_counters';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    /**
     * Obtiene el siguiente folio de forma atómica (thread-safe).
     * Usar SIEMPRE dentro de una transacción.
     */
    public static function nextFolio(int $clubId, int $fileId): string
    {
        return DB::transaction(function () use ($clubId, $fileId) {
            $counter = static::where('club_id', $clubId)
                ->where('file_id', $fileId)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                $counter = static::create([
                    'club_id'       => $clubId,
                    'file_id'       => $fileId,
                    'current_value' => 1,
                ]);
            } else {
                $counter->increment('current_value');
            }

            return str_pad($counter->current_value, 8, '0', STR_PAD_LEFT);
        });
    }
}
