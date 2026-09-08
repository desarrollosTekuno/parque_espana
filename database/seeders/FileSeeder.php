<?php

namespace Database\Seeders;

use App\Models\Files\File;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formatos = [
            [
                'code' => 'SOL_USER_CL',
                'name' => 'Solicitud de usuario con logo',
                'description' => 'Solicitud de usuario con logo',
                'is_required' => true,
                'is_active' => true,
                'allowed_mime_types' => [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                'max_size_bytes' => 2097152,
                'module' => 'Administración',
            ],
            [
                'code' => 'SOL_USER_SL',
                'name' => 'Solicitud de usuario sin logo',
                'description' => 'Solicitud de usuario sin logo',
                'is_required' => true,
                'is_active' => true,
                'allowed_mime_types' => [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                'max_size_bytes' => 2097152,
                'module' => 'Administración',
            ],
            [
                'code' => 'SOL_PERMISO',
                'name' => 'Solicitud de permiso',
                'description' => 'Solicitud de permiso',
                'is_required' => true,
                'is_active' => true,
                'allowed_mime_types' => [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                'max_size_bytes' => 2097152,
                'module' => 'Administración',
            ],
            [
                'code' => 'SOL_LOCKER',
                'name' => 'Solicitud de locker',
                'description' => 'Solicitud de locker',
                'is_required' => true,
                'is_active' => true,
                'allowed_mime_types' => [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                'max_size_bytes' => 2097152,
                'module' => 'Administración',
            ],
        ];
        
        foreach ($formatos as $formato) {
            File::updateOrCreate(
                ['code' => $formato['code']],
                $formato
            );
        }
    }
}
