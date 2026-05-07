<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LockerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding lockers (idempotente)...');

        // PARQUE 1
        $this->seedClub(1, [
            ['ninos', 1, 65],
            ['ninas', 1, 65],
            ['caballeros', 1, 1400],
            ['damas', 1, 582],
        ]);

        // PARQUE 2
        $this->seedClub(2, [
            ['damas', 1, 1170],
            ['caballeros', 1, 1926],
            ['ninas', 1, 288],
            ['ninos', 1, 242],
        ]);

        $this->command->info('✔ Seeder terminado sin duplicados');
    }

    private function seedClub($clubId, $ranges)
    {
        foreach ($ranges as [$category, $start, $end]) {
            $this->command->info("Club {$clubId} - {$category}");

            $this->upsertRange($clubId, $category, $start, $end);
        }
    }

    private function upsertRange($clubId, $category, $start, $end)
    {
        $batch = [];

        for ($i = $start; $i <= $end; $i++) {
            $batch[] = [
                'club_id' => $clubId,
                'number' => $i,
                'category' => $category,
                'status' => 'disponible',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) === 100) {
                $this->doUpsert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->doUpsert($batch);
        }
    }

    private function doUpsert($data)
    {
        DB::table('members.lockers')->upsert(
            $data,
            ['club_id', 'number', 'category'], // clave única
            ['status', 'updated_at'] // campos a actualizar si ya existe
        );
    }
}