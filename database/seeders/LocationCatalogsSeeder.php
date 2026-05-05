<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class LocationCatalogsSeeder extends Seeder {
    private array $countries = ['MX', 'ES', 'US'];

    public function run(): void {
        $path = database_path('data');

        Artisan::call('catalogs:import-locations', [
            '--source' => 'dr5hn',
            '--path' => $path,
            '--country' => $this->countries,
            '--include-cities' => true,
        ]);

        if ($this->command) {
            $this->command->line(Artisan::output());
        }
    }
}
