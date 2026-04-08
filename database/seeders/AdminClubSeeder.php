<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminClubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $adminClubPermissions = array(
            'dashboard',
            'profile.show',
            'announcements.index',
            'announcements.store',
            'announcements.update',
            'announcements.destroy',
            'amenities.index',
            'amenities.store',
            'amenities.update',
            'amenities.destroy',
            'amenityResource.index',
            'amenityResource.store',
            'amenityResource.update',
            'amenityResource.destroy',
            'reservations.index',
            'reservations.update',
            'system-variables.index',
            'system-variables.store',
            'system-variables.update',
            'system-variables.destroy',
            'members.index',
            'members.create',
            'members.store',
            'members.edit',
            'members.update',
            'members.destroy',
        );
        $adminClubRole = Role::updateOrCreate([
            'name' => 'admin_club',
        ], [
            'description' => 'Administrador del club',
            'context_id' => Context::firstOrCreate([
                'value' => 'web',
            ], [
                'name' => 'Web',
                'value' => 'web'
            ])->id
        ]);
        $adminClubRole->syncPermissions($adminClubPermissions);
    }
}
