<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use FeedbackCategoriesSeeder;
use FeedbackPrioritiesSeeder;
use FeedbackStatusesSeeder;
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
            RoleSeeder::class,
            SuperAdminSeeder::class,
            AdminClubSeeder::class,
            ClubSeeder::class,
            UserSeeder::class,
            ReservationStatusSeeder::class,
            SystemVariableSeeder::class,
            DocumentTypeSeeder::class,
            RelationshipSeeder::class,
            NationalitySeeder::class,
            MaritalStatusSeeder::class,
            BillingConceptSeeder::class,
            PaymentMethodSeeder::class,
            ClubPaymentMethodSeeder::class,
            RelationshipDocumentTypesSeeder::class,
            MembershipTypeSeeder::class,
            AmenitySeeder::class,
            PricingRuleSeeder::class,
            MembershipTypeRequiredDocumentSeeder::class,
            InterclubPackageRuleSeeder::class,
            BillingConceptSeeder::class,
            PaymentMethodSeeder::class,
            ClubPaymentMethodSeeder::class,

            FeedbackCategoriesSeeder::class,
            FeedbackTicketTypesSeeder::class,
            FeedbackStatusesSeeder::class,
            FeedbackPrioritiesSeeder::class,
        ]);
    }
}
