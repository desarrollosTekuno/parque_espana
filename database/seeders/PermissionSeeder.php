<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = array(
            array('name' => 'dashboard', 'description' => 'Acceso al dashboard', 'contexts' => ['web']),
            array('name' => 'profile.show', 'description' => 'Ver perfil', 'contexts' => ['web']),
            array('name' => 'permissions.index', 'description' => 'Ver permisos', 'contexts' => ['web']),
            array('name' => 'permissions.store', 'description' => 'Crear permisos', 'contexts' => ['web']),
            array('name' => 'permissions.update', 'description' => 'Actualizar permisos', 'contexts' => ['web']),
            array('name' => 'permissions.destroy', 'description' => 'Eliminar permisos', 'contexts' => ['web']),
            array('name' => 'roles.index', 'description' => 'Ver roles', 'contexts' => ['web']),
            array('name' => 'roles.store', 'description' => 'Crear roles', 'contexts' => ['web']),
            array('name' => 'roles.update', 'description' => 'Actualizar roles', 'contexts' => ['web']),
            array('name' => 'roles.destroy', 'description' => 'Eliminar roles', 'contexts' => ['web']),
            array('name' => 'roles.duplicate', 'description' => 'Duplicar roles', 'contexts' => ['web']),
            // Users
            array('name' => 'users.index', 'description' => 'Ver usuarios', 'contexts' => ['web']),
            array('name' => 'users.store', 'description' => 'Crear usuarios', 'contexts' => ['web']),
            array('name' => 'users.update', 'description' => 'Actualizar usuarios', 'contexts' => ['web']),
            array('name' => 'users.destroy', 'description' => 'Eliminar usuarios', 'contexts' => ['web']),
            // Clubs
            array('name' => 'clubs.index', 'description' => 'Ver clubes', 'contexts' => ['web']),
            array('name' => 'clubs.store', 'description' => 'Crear clubes', 'contexts' => ['web']),
            array('name' => 'clubs.update', 'description' => 'Actualizar clubes', 'contexts' => ['web']),
            array('name' => 'clubs.destroy', 'description' => 'Eliminar clubes', 'contexts' => ['web']),
            // Amenidades
            array('name' => 'amenities.index', 'description' => 'Ver amenidades', 'contexts' => ['web']),
            array('name' => 'amenities.store', 'description' => 'Crear amenidades', 'contexts' => ['web']),
            array('name' => 'amenities.update', 'description' => 'Actualizar amenidades', 'contexts' => ['web']),
            array('name' => 'amenities.destroy', 'description' => 'Eliminar amenidades'),
            // Reservaciones
            array('name' => 'reservations.index', 'description' => 'Ver reservaciones', 'contexts' => ['web']),
            array('name' => 'reservations.update', 'description' => 'Cancelar reservaciones', 'contexts' => ['web']),
            // Anuncios
            array('name' => 'announcements.index', 'description' => 'Ver anuncios', 'contexts' => ['web']),
            array('name' => 'announcements.store', 'description' => 'Crear anuncios', 'contexts' => ['web']),
            array('name' => 'announcements.update', 'description' => 'Actualizar anuncios', 'contexts' => ['web']),
            array('name' => 'announcements.destroy', 'description' => 'Eliminar anuncios'),
            // Variables de sistema
            array('name' => 'system-variables.index', 'description' => 'Ver variables de sistema', 'contexts' => ['web']),
            array('name' => 'system-variables.store', 'description' => 'Crear variables de sistema', 'contexts' => ['web']),
            array('name' => 'system-variables.update', 'description' => 'Actualizar variables de sistema', 'contexts' => ['web']),
            array('name' => 'system-variables.destroy', 'description' => 'Eliminar variables de sistema', 'contexts' => ['web']),

        );
        foreach ($permissions as $permission) {
            $createPermission= Permission::updateOrCreate(['name' => $permission['name']], [
                'description' => $permission['description'],
            ]);
            if (isset($permission['contexts'])) {
                $createPermission->contexts()->sync(
                    array_map(function ($context) {
                        return Context::firstOrCreate([
                            'value' => $context
                        ], ['name' => $context, 'value' => $context])->id;
                    }, $permission['contexts'])
                );
            }else{
                // Sync with web context by default
                $createPermission->contexts()->sync([
                    Context::firstOrCreate(['value' => 'web'], ['name' => 'Web', 'value' => 'web'])->id
                ]);
            }
        }
    }
}
