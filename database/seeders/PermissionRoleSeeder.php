<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionRoleSeeder extends Seeder
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
        );
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }


        $roles = array(
            array('name' => 'superadmin', 'description' => 'Super administrador'),
        );
        foreach ($roles as $role) {
            Role::create($role);
        }

        /*Define superadmin permissions */
        $superadminPermissions = array(
            'dashboard',
            'profile.show',
            'permissions.index',
            'permissions.store',
            'permissions.update',
            'permissions.destroy',
            'roles.index',
            'roles.store',
            'roles.update',
            'roles.destroy',
            'roles.duplicate'
        );
        $superadmin = Role::where('name', 'superadmin')->first();
        $superadmin->syncPermissions($superadminPermissions);
    }
}
