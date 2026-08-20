<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $superadmin = Role::firstOrCreate([
            'name' => 'superadmin',
            'description' => 'Super Administrador',
            'context_id' => Context::firstOrCreate([
                'value' => 'web',
            ], [
                'name' => 'Web',
                'value' => 'web'
            ])->id
        ]);

        /*Define superadmin permissions */
        $superadminPermissions = array(
            'profile.show',
            'permissions.index',
            'permissions.store',
            'permissions.update',
            'permissions.destroy',
            'roles.index',
            'roles.store',
            'roles.update',
            'roles.destroy',
            'roles.duplicate',
            'users.index',
            'users.store',
            'users.update',
            'users.destroy',
            'clubs.index',
            'clubs.store',
            'clubs.update',
            'clubs.destroy',
            'conekta-credentials.index',
            'conekta-credentials.update',
            'specialties.index',
            'specialties.store',
            'specialties.update',
            'specialties.destroy',
            'website-content.index',
            'website-content.store',
            'website-content.destroy',
            'website-contacts.index',
        );
        $superadmin->syncPermissions($superadminPermissions);
    }
}
