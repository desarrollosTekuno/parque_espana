<?php

namespace Database\Seeders;

use App\Models\Catalogs\Relationship;
use Illuminate\Database\Seeder;

class RelationshipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $relationships = [
            [
                'name' => 'Titular',
            ],
            [
                'name' => 'Cónyuge',
            ],
            [
                'name' => 'Hijo(a)',
            ],
            [
                'name' => 'Madre',
            ]
        ];

        foreach ($relationships as $relationship) {
            Relationship::create($relationship);
        }
    }
}
