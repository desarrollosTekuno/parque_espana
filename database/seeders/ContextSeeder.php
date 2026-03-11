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
            // App móvil socios
            array('name' => "App Móvil Socios", 'value' => 'mobile_app')
        );
        foreach ($contexts as $context) {
            Context::updateOrCreate(['value' => $context['value']], $context);
        }
    }
}
