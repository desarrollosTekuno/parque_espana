<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            ['label' => 'Carta',        'price' => 15.00, 'display_order' => 1],
            ['label' => 'Oficio',       'price' => 20.00, 'display_order' => 2],
            ['label' => 'Doble Carta',  'price' => 30.00, 'display_order' => 3],
            ['label' => 'Doble Oficio', 'price' => 40.00, 'display_order' => 4],
        ];

        $clubIds = DB::table('clubs.clubs')->pluck('id');
        $now = now();

        foreach ($clubIds as $clubId) {
            foreach ($defaults as $d) {
                DB::table('advertising.physical_ad_sizes')->insertOrIgnore([
                    'club_id'       => $clubId,
                    'label'         => $d['label'],
                    'price'         => $d['price'],
                    'is_active'     => true,
                    'display_order' => $d['display_order'],
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('advertising.physical_ad_sizes')->whereIn(
            'label', ['Carta', 'Oficio', 'Doble Carta', 'Doble Oficio']
        )->delete();
    }
};
