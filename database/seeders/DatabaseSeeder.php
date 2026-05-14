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
            RoleSeeder::class,
            SuperAdminSeeder::class,
            AdminClubSeeder::class,
            LocationCatalogsSeeder::class,
            ClubSeeder::class,
            UserSeeder::class,
            SystemVariableSeeder::class,
            NotificationChannelSeeder::class,
            NotificationStatusCatalogSeeder::class,
            EmailConfigSeeder::class,
            ReservationStatusSeeder::class,
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
            LockerSeeder::class,

            FeedbackCategoriesSeeder::class,
            FeedbackTicketTypesSeeder::class,
            FeedbackStatusesSeeder::class,
            FeedbackPrioritiesSeeder::class,
        ]);
    }
}
