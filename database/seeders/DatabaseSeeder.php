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
            ClassReservationModuleSeeder::class,
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
            SeparationReasonSeeder::class,
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
            LockerSeeder::class,

            FeedbackCategoriesSeeder::class,
            FeedbackTicketTypesSeeder::class,
            FeedbackStatusesSeeder::class,
            FeedbackPrioritiesSeeder::class,

            GuestListVariableSeeder::class,

            SpecialtySeeder::class,
            // CoachSeeder::class, //Solo pruebas

            SeparationReasonSeeder::class //Catalogo de motivos de separacion
        ]);
    }
}
