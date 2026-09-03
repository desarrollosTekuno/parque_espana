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
            AdminSubRolesSeeder::class,
            LocationCatalogsSeeder::class,
            ClubSeeder::class,
            ClubAddressSeeder::class,
            ClubContactSeeder::class,
            UserSeeder::class,
            // UserCodeSeeder::class,
            SystemVariableSeeder::class,
            AppVariableSeeder::class,
            NotificationChannelSeeder::class,
            NotificationStatusCatalogSeeder::class,
            EmailConfigSeeder::class,
            ReservationStatusSeeder::class,
            DocumentTypeSeeder::class,
            RelationshipSeeder::class,
            SeparationReasonSeeder::class,
            CancellationReasonSeeder::class,
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
            PhysicalAdSizeSeeder::class,
            AnnualDiscountRuleSeeder::class,

            //  php artisan migrate:data "C:\Apache24\htdocs\ParquesEsp\public\Plantilla_Migracion_Casos_Prueba_tmp.xlsx" --only=usuarios,membresias,integrantes

        ]);
    }
}
