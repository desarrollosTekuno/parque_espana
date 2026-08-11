<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\MobileApp\AppVariable;
use Illuminate\Database\Seeder;

class AppVariableSeeder extends Seeder {
    public function run(): void {
        $clubIds = Club::pluck('id');

        foreach ($clubIds as $clubId) {
            AppVariable::updateOrCreate(
                [
                    'name' => 'default_user_password',
                    'club_id' => $clubId,
                ],
                [
                    'description' => 'CONTRASEÑA DE USUARIO POR DEFECTO',
                    'value' => 'parque26',
                ]
            );
        }
    }
}
