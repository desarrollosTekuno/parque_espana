<?php

namespace Database\Seeders;

use App\Models\AdminClub\ReservationStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReservationStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $status = [
            ['name' => 'ACTIVA', 'color' => 'green'],
            ['name' => 'CANCELADA', 'color' => 'red'],
            ['name' => 'FINALIZADA', 'color' => 'blue'],
            ['name' => 'INASISTENCIA', 'color' => 'red']
        ];

        foreach ($status as $stat){
            ReservationStatus::create($stat);
        }
    }
}
