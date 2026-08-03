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

    }
}

// Segun yo seria asi
// Inicio: Carousel Subir de 1 a 5 imagenes
// Inicio: Cards de Gimnasio, Alberca, Tenis, Jardines, Cafetería (Maximo 10, 2 para cada categoria)
// Membresias: Solo precios
// Instalaciones: Vista virtual - En grid crear modulo para cargar categorías e imágenes (Interios, Exterior, Serivioc, Actividad fisica, Estacionamiento por default)
// Instalaciones: Api para eventos, (se van a visualizar en un calendario)

// Todas deben tener un tamaño minimo y se van a redimencionar de momento tu dales un tamaño que consideres que se va a ver bien en la pagina, tambien se van a convertir a .web las imagenes que suban
