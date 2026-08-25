<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhysicalAdSizeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['label' => 'Carta',        'price' => 15.00, 'display_order' => 1],
            ['label' => 'Oficio',       'price' => 20.00, 'display_order' => 2],
            ['label' => 'Doble Carta',  'price' => 30.00, 'display_order' => 3],
            ['label' => 'Doble Oficio', 'price' => 40.00, 'display_order' => 4],
        ];

        $now = now();

        $clubIds = DB::table('clubs.clubs')->pluck('id');

        foreach ($clubIds as $clubId) {
            foreach ($defaults as $size) {
                DB::table('advertising.physical_ad_sizes')
                    ->updateOrInsert(
                        [
                            'club_id' => $clubId,
                            'label' => $size['label'],
                        ],
                        [
                            'price' => $size['price'],
                            'is_active' => true,
                            'display_order' => $size['display_order'],
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
            }
        }
    }
}