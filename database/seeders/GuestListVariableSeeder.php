<?php

namespace Database\Seeders;

use App\Models\AdminClub\GuestListVariable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GuestListVariableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $variables = [
            [
                'code' => 'MAX_GUESTS',
                'name' => 'Máximo de invitados',
                'description' => 'Número máximo de invitados permitidos por el club',
                'value' => 100,
                'club_id' => 1
            ],
            [
                'code' => 'NORMAL_PRICE',
                'name' => 'Costo por invitado normal',
                'description' => 'Costo por invitado con edad mayor o igual a 7 años',
                'value' => 300,
                'club_id' => 1
            ],
            [
                'code' => 'SPECIAL_PRICE',
                'name' => 'Costo por invitado especial',
                'description' => 'Costo por invitado con edad entre 3 y 6 años',
                'value' => 150,
                'club_id' => 1
            ],
            [
                'code' => 'MAX_GUESTS',
                'name' => 'Máximo de invitados',
                'description' => 'Número máximo de invitados permitidos por el club',
                'value' => 12,
                'club_id' => 2
            ],
            [
                'code' => 'NORMAL_PRICE',
                'name' => 'Costo por invitado normal',
                'description' => 'Costo por invitado con edad mayor o igual a 7 años',
                'value' => 400,
                'club_id' => 2
            ],
            [
                'code' => 'SPECIAL_PRICE',
                'name' => 'Costo por invitado especial',
                'description' => 'Costo por invitado con edad entre 3 y 6 años',
                'value' => 200,
                'club_id' => 2
            ],
            [
                'code' => 'MIN_AGE',
                'name' => 'Edad mínima para pagar',
                'description' => 'Edad mínima para que un invitado tenga que pagar el pase de día',
                'value' => 3,
                'club_id' => 1
            ],
            [
                'code' => 'MAX_AGE',
                'name' => 'Edad máxima para pagar',
                'description' => 'Edad máxima para que un invitado tenga que pagar el pase de día',
                'value' => 6,
                'club_id' => 1
            ],
            [
                'code' => 'MIN_AGE',
                'name' => 'Edad mínima para pagar',
                'description' => 'Edad mínima para que un invitado tenga que pagar el pase de día',
                'value' => 3,
                'club_id' => 2
            ],
            [
                'code' => 'MAX_AGE',
                'name' => 'Edad máxima para pagar',
                'description' => 'Edad máxima para que un invitado tenga que pagar el pase de día',
                'value' => 6,
                'club_id' => 2
            ],
        ];

        foreach ($variables as $variable) {
            GuestListVariable::updateOrCreate(
                [
                    'code' => $variable['code'],
                    'club_id' => $variable['club_id'],
                ],
                [
                    'name' => $variable['name'],
                    'description' => $variable['description'],
                    'value' => $variable['value'],
                ]
            );
        }
    }
}
