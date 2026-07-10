<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ClassReservationModuleSeeder extends Seeder
{
    public function run(): void
    {
        $context = Context::firstOrCreate([
            'value' => 'web',
        ], [
            'name' => 'Web',
            'value' => 'web',
        ]);

        $permissions = [
            [
                'module' => 'Clases',
                'name' => 'classReservations.index',
                'description' => 'Ver reservaciones de clases',
            ],
            [
                'module' => 'Clases',
                'name' => 'classReservations.store',
                'description' => 'Crear reservaciones de clases',
            ],
            [
                'module' => 'Clases',
                'name' => 'classReservations.cancel',
                'description' => 'Cancelar reservaciones de clases',
            ],
        ];

        foreach ($permissions as $permission) {
            $record = Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                    'guard_name' => 'web',
                ]
            );

            $record->contexts()->syncWithoutDetaching([$context->id]);
        }

        $adminClubRole = Role::where('name', 'admin_club')->first();

        if ($adminClubRole) {
            $adminClubRole->givePermissionTo(collect($permissions)->pluck('name')->all());
        }
    }
}
