<?php

namespace Database\Seeders;

use App\Models\Context;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $contexts = array(
            array('name' => 'Web', 'value' => 'web'),
            array('name' => 'Api', 'value' => 'api'),
            // App móvil socios por club
            array('name' => "App Móvil - Parque España 1", 'value' => 'mobile_club_1'),
            array('name' => "App Móvil - Parque España 2", 'value' => 'mobile_club_2'),
        );
        foreach ($contexts as $context) {
            Context::updateOrCreate(['value' => $context['value']], $context);
        }
    }
}
