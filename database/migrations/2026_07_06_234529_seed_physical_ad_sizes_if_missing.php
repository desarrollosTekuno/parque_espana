<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
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
            foreach ($defaults as $size) {
                DB::table('advertising.physical_ad_sizes')->updateOrInsert(
                    [
                        'club_id' => $clubId,
                        'label'   => $size['label'],
                    ],
                    [
                        'price'         => $size['price'],
                        'is_active'     => true,
                        'display_order' => $size['display_order'],
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        
    }
};
