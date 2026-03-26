<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clubs = [
            [
                'name' => 'Parque España 1',
                'address' => 'Calle 1',
                'is_active' => true
            ],
            [
                'name' => 'Parque España 2',
                'address' => 'Calle 2',
                'is_active' => true
            ]
        ];

        foreach ($clubs as $club) {
            Club::create($club);
        }
    }
}
