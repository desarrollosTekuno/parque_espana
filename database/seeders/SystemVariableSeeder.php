<?php

namespace Database\Seeders;

use App\Models\AdminClub\SystemVariable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemVariableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $system_variables = [
            [
                'name' => 'dias_para_crear_reserva',
                'value' => '2',
                'description' => 'Total de días en los que el socio puede crear reservaciones (día actual y siguiente)',
                'club_id' => 1
            ],
            [
                'name' => 'dias_para_cancelar_reserva',
                'value' => '2',
                'description' => 'Total de días en los que el socio puede cancelar reservaciones',
                'club_id' => 1
            ],
            [
                'name' => 'horas_suspension_reserva',
                'value' => '48',
                'description' => 'Total de horas de suspensión para reservar',
                'club_id' => 1
            ],
        ];

        foreach ($system_variables as $system_variable) {
            SystemVariable::create($system_variable);
        }

    }
}
