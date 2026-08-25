<?php

namespace Database\Seeders;

use App\Models\AdminClub\BusinessAd;
use App\Models\AdminClub\BusinessCategory;
use App\Models\Administrator\Club;
use App\Models\Members\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BusinessAdsSeeder extends Seeder
{
    private const EJERCICIO_IMAGE = 'C:/Users/OsirisTK/Downloads/Ejercicio.png';
    private const ALIMENTACION_IMAGE = 'C:/Users/OsirisTK/Downloads/Alimentacion.png';

    public function run(): void
    {
        $categoriesByClub = [
            'Educacion',
            'Salud',
            'Gastronomia',
            'Tecnologia',
        ];

        $adsByCategory = [
            'Educacion' => [
                [
                    'name' => 'Instituto Alameda',
                    'description' => 'Clases particulares y cursos de regularizacion.',
                    'address' => 'Av. Reforma 101',
                    'phone' => '5551001001',
                    'email' => 'contacto@institutoalameda.test',
                    'website' => 'https://institutoalameda.test',
                    'status_id' => 5,
                ],
            ],
            'Salud' => [
                [
                    'name' => 'Clinica Vital',
                    'description' => 'Consulta general y servicios de bienestar.',
                    'address' => 'Calle Salud 220',
                    'phone' => '5552002002',
                    'email' => 'hola@clinicavital.test',
                    'website' => 'https://clinicavital.test',
                    'status_id' => 5,
                    'image_source' => self::EJERCICIO_IMAGE,
                ],
            ],
            'Gastronomia' => [
                [
                    'name' => 'Sabores del Parque',
                    'description' => 'Cocina casera para socios y visitantes.',
                    'address' => 'Plaza Central 15',
                    'phone' => '5553003003',
                    'email' => 'reservas@saboresdelparque.test',
                    'website' => 'https://saboresdelparque.test',
                    'status_id' => 5,
                    'image_source' => self::ALIMENTACION_IMAGE,
                ],
            ],
            'Tecnologia' => [
                [
                    'name' => 'Soporte Digital PE',
                    'description' => 'Reparacion, venta de accesorios y asesoria.',
                    'address' => 'Boulevard Tecnologia 88',
                    'phone' => '5554004004',
                    'email' => 'ventas@soportedigitalpe.test',
                    'website' => 'https://soportedigitalpe.test',
                    'status_id' => 1,
                ],
            ],
        ];

        Club::query()->orderBy('id')->each(function (Club $club) use ($categoriesByClub, $adsByCategory) {
            $member = Member::byClub($club->id)->first();
            $categoryImages = [
                'Salud' => $this->uploadCategoryImage($club, 'salud', self::EJERCICIO_IMAGE),
                'Gastronomia' => $this->uploadCategoryImage($club, 'gastronomia', self::ALIMENTACION_IMAGE),
            ];

            if (!$member) {
                $member = Member::factory()
                    ->individualWithUserAndMembership($club->id)
                    ->create();
            }

            foreach ($categoriesByClub as $categoryName) {
                $category = BusinessCategory::updateOrCreate(
                    [
                        'club_id' => $club->id,
                        'name' => $categoryName,
                    ],
                    [
                        'is_active' => true,
                        'image' => $categoryImages[$categoryName] ?? null,
                    ]
                );

                foreach ($adsByCategory[$categoryName] as $adData) {
                    $statusId = $adData['status_id'];

                    BusinessAd::updateOrCreate(
                        [
                            'club_id' => $club->id,
                            'member_id' => $member->id,
                            'name' => $adData['name'],
                        ],
                        [
                            'category_id' => $category->id,
                            'description' => $adData['description'],
                            'image' => $this->uploadAdImage(
                                $club,
                                $adData['name'],
                                $adData['image_source'] ?? null
                            ),
                            'address' => $adData['address'],
                            'phone' => $adData['phone'],
                            'email' => $adData['email'],
                            'website' => $adData['website'],
                            'status_id' => $statusId,
                            'approved_at' => $statusId === 5 ? now()->subDays(5) : null,
                            'paid_at' => $statusId === 5 ? now()->subDays(4) : null,
                            'published_at' => $statusId === 5 ? now()->subDays(4) : null,
                            'expires_at' => $statusId === 5 ? now()->addDays(26) : null,
                            'rejection_reason' => null,
                        ]
                    );
                }
            }
        });
    }

    private function uploadCategoryImage(Club $club, string $name, ?string $sourcePath): ?string
    {
        if (!$sourcePath || !is_file($sourcePath)) {
            return null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $path = "clubs/{$club->code}/business-categories/{$name}.{$extension}";

        Storage::disk('spaces')->put($path, file_get_contents($sourcePath), 'public');

        return $path;
    }

    private function uploadAdImage(Club $club, string $name, ?string $sourcePath): ?string
    {
        if (!$sourcePath || !is_file($sourcePath)) {
            return null;
        }

        $slug = str($name)->lower()->slug('-')->value();
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $path = "clubs/{$club->code}/business-ads/{$slug}.{$extension}";

        Storage::disk('spaces')->put($path, file_get_contents($sourcePath), 'public');

        return Storage::disk('spaces')->url($path);
    }
}
