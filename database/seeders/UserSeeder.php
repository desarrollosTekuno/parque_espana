<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $user = User::firstOrCreate([
        //     'name' => 'Superadministrador',
        //     'email' => 'superadmin@tekuno.mx',
        //     'password' => bcrypt('Pa$$w0rd'),
        // ]);
        // $user->assignRole('superadmin');
        $superAdmin = User::factory()->superAdministrator()->create([
            'name' => 'Superadministrador',
            'email' => 'superadmin@tekuno.mx',
            'password' => bcrypt('Pa$$w0rd'),
        ]);

        $adminClubs = User::factory()->administratorClub()->create([
            'name' => 'Administrador del Club',
            'email' => 'antoniotoxquisosa@hotmail.com',
            'password' => bcrypt('Pa$$w0rd'),
        ]);
        // Assign clubs to the admin club user
        $adminClubs->clubs()->attach([1, 2]); // Assuming club IDs 1 and 2 exist
        // Crear usuario para integración de apis con página web del club
        $apiUser = User::factory()->create([
            'name' => 'Usuario API Web',
            'email' => 'apiuser@tekuno.mx',
            'password' => bcrypt('Pa$$w0rd'),
        ]);

    }
}

