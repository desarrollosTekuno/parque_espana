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
        User::factory()->superAdministrator()->create([
            'name' => 'Superadministrador',
            'email' => 'superadmin@tekuno.mx',
            'password' => bcrypt('Pa$$w0rd'),
        ]);
        User::factory()->administratorClub()->create([
            'name' => 'Administrador del Club',
            'email' => 'antoniotoxquisosa@hotmail.com',
            'password' => bcrypt('Pa$$w0rd'),
        ]);
    }
}
