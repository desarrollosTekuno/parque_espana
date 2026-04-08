<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            ContextSeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            AdminClubSeeder::class,
            UserSeeder::class,
            ClubSeeder::class,
            ReservationStatusSeeder::class,
            SystemVariableSeeder::class,
            DocumentTypeSeeder::class,
            RelationshipSeeder::class,
            NationalitySeeder::class,
            MaritalStatusSeeder::class,
            RelationshipDocumentTypesSeeder::class,
            MembershipTypeSeeder::class,
            AmenitySeeder::class,
            PricingRuleSeeder::class,
            MembershipTypeRequiredDocumentSeeder::class,
            InterclubPackageRuleSeeder::class,
        ]);
    }
}
