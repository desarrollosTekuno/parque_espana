<?php

namespace Database\Seeders;

use App\Models\AdminClub\SystemVariable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemVariableSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $systemVariables = [
            [
                'name' => 'dias_para_crear_reserva',
                'value' => '2',
                'description' => 'Total de días en los que el usuario puede crear reservaciones (día actual y siguiente)',
                'club_id' => 1
            ],
            [
                'name' => 'dias_para_cancelar_reserva',
                'value' => '2',
                'description' => 'Total de días en los que el usuario puede cancelar reservaciones',
                'club_id' => 1
            ],
            [
                'name' => 'horas_suspension_reserva',
                'value' => '48',
                'description' => 'Total de horas de suspensión para reservar',
                'club_id' => 1
            ],
            [
                'name' => 'feedback_notification_email',
                'value' => 'notificaciones@parquesespana.com',
                'description' => 'Correo del Club PE1 que recibe notificaciones de nuevas quejas/sugerencias y cancelaciones',
                'club_id' => 1
            ],
            [
                'name' => 'reservaciones_por_dia',
                'value' => '1',
                'description' => 'Número máximo de reservaciones que un usuario puede tener en un mismo día',
                'club_id' => 2
            ],
            [
                'name' => 'feedback_notification_email',
                'value' => 'notificaciones@parquesespana.com',
                'description' => 'Correo del Club PE2 que recibe notificaciones de nuevas quejas/sugerencias y cancelaciones',
                'club_id' => 2
            ],
        ];

        foreach ($systemVariables as $systemVariable) {
            SystemVariable::updateOrCreate(
                [
                    'name' => $systemVariable['name'],
                    'club_id' => $systemVariable['club_id'],
                ],
                [
                    'value' => $systemVariable['value'],
                    'description' => $systemVariable['description'],
                ]
            );
        }

    }
}
