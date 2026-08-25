<?php

namespace Database\Seeders;

use App\Models\Catalogs\CancellationReason;
use Illuminate\Database\Seeder;

class CancellationReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            ['code' => 'nonpayment', 'name' => 'Falta de Pago'],
            ['code' => 'death', 'name' => 'Defunción'],
            ['code' => 'divorce', 'name' => 'Divorcio'],
            ['code' => 'expulsion', 'name' => 'Expulsión'],
            ['code' => 'permit', 'name' => 'Permiso'],
            ['code' => 'voluntary', 'name' => 'Voluntaria'],
        ];

        foreach ($reasons as $reason) {
            CancellationReason::updateOrCreate(
                ['code' => $reason['code']],
                ['name' => $reason['name'], 'is_active' => true]
            );
        }
    }
}
