<?php

namespace Database\Seeders;

use App\Models\Catalogs\MaritalStatus;
use Illuminate\Database\Seeder;

class MaritalStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $maritalStatuses = [
            ['code' => 'single', 'name' => 'Soltero(a)'],
            ['code' => 'married', 'name' => 'Casado(a)'],
            ['code' => 'divorced', 'name' => 'Divorciado(a)'],
            ['code' => 'widowed', 'name' => 'Viudo(a)'],
            ['code' => 'separated', 'name' => 'Separado(a)'],
            ['code' => 'free_union', 'name' => 'Union libre'],
            ['code' => 'domestic_partnership', 'name' => 'Concubinato'],
        ];

        foreach ($maritalStatuses as $maritalStatus) {
            MaritalStatus::updateOrCreate(
                ['code' => $maritalStatus['code']],
                ['name' => $maritalStatus['name']]
            );
        }
    }
}
