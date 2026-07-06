<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            [
                'code'        => 'CAFETERIA_MIN_CONSUMPTION',
                'name'        => 'Consumo mínimo cafetería',
                'description' => 'Monto mínimo de consumo en cafetería para exentar el cobro de acceso al parque',
                'value'       => '300',
            ],
            [
                'code'        => 'CAFETERIA_DURATION_HOURS',
                'name'        => 'Horas de estancia por cafetería',
                'description' => 'Duración máxima de estancia en el parque cuando el acceso es por consumo en cafetería',
                'value'       => '2',
            ],
        ];

        $clubIds = DB::table('clubs.clubs')->pluck('id');
        $now = now();

        foreach ($clubIds as $clubId) {
            foreach ($defaults as $v) {
                DB::table('guest_lists.variables')->insertOrIgnore([
                    'code'        => $v['code'],
                    'name'        => $v['name'],
                    'description' => $v['description'],
                    'value'       => $v['value'],
                    'club_id'     => $clubId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('guest_lists.variables')
            ->whereIn('code', ['CAFETERIA_MIN_CONSUMPTION', 'CAFETERIA_DURATION_HOURS'])
            ->delete();
    }
};
