<?php

namespace Database\Seeders;

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
            array('name' => 'dashboard', 'description' => 'Acceso al dashboard'),
            array('name' => 'profile.show', 'description' => 'Ver perfil'),
            array('name' => 'permissions.index', 'description' => 'Ver permisos'),
            array('name' => 'permissions.store', 'description' => 'Crear permisos'),
            array('name' => 'permissions.update', 'description' => 'Actualizar permisos'),
            array('name' => 'permissions.destroy', 'description' => 'Eliminar permisos'),
            array('name' => 'roles.index', 'description' => 'Ver roles'),
            array('name' => 'roles.store', 'description' => 'Crear roles'),
            array('name' => 'roles.update', 'description' => 'Actualizar roles'),
            array('name' => 'roles.destroy', 'description' => 'Eliminar roles'),
            array('name' => 'roles.duplicate', 'description' => 'Duplicar roles'),
            // Users
            array('name' => 'users.index', 'description' => 'Ver usuarios'),
            array('name' => 'users.store', 'description' => 'Crear usuarios'),
            array('name' => 'users.update', 'description' => 'Actualizar usuarios'),
            array('name' => 'users.destroy', 'description' => 'Eliminar usuarios'),
            // Clubs
            array('name' => 'clubs.index', 'description' => 'Ver clubes'),
            array('name' => 'clubs.store', 'description' => 'Crear clubes'),
            array('name' => 'clubs.update', 'description' => 'Actualizar clubes'),
            array('name' => 'clubs.destroy', 'description' => 'Eliminar clubes'),
            // Amenidades
            array('name' => 'amenities.index', 'description' => 'Ver amenidades'),
            array('name' => 'amenities.store', 'description' => 'Crear amenidades'),
            array('name' => 'amenities.update', 'description' => 'Actualizar amenidades'),
            array('name' => 'amenities.destroy', 'description' => 'Eliminar amenidades'),
        );
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
