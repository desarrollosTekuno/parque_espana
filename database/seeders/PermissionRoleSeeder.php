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
            
        );
        $superadmin = Role::where('name', 'superadmin')->first();
        $superadmin->syncPermissions($superadminPermissions);
    }
}
