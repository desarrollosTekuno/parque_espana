<?php

namespace Database\Seeders;

use App\Models\MobileApp\AppVariable;
use Illuminate\Database\Seeder;

class AppVariableSeeder extends Seeder {
    public function run(): void {
        AppVariable::where('name', 'default_user_password')
            ->whereNotNull('club_id')
            ->forceDelete();

        $variable = AppVariable::withTrashed()->firstOrNew([
            'name' => 'default_user_password',
            'club_id' => null,
        ]);

        $variable->description = 'CONTRASEÑA DE USUARIO POR DEFECTO';
        $variable->value = 'parque26';
        $variable->deleted_at = null;
        $variable->save();
    }
}
