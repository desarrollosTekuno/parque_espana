<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminClubSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $adminClubPermissions = array(
            'profile.show',
            'announcements.index',
            'announcements.store',
            'announcements.update',
            'announcements.destroy',
            'amenities.index',
            'amenities.store',
            'amenities.update',
            'amenities.destroy',
            'amenityResource.index',
            'amenityResource.store',
            'amenityResource.update',
            'amenityResource.destroy',
            'reservations.index',
            'reservations.update',
            'system-variables.index',
            'system-variables.store',
            'system-variables.update',
            'system-variables.destroy',
            'members.index',
            'members.create',
            'members.store',
            'members.edit',
            'members.update',
            'members.destroy',
            'members.reactivate',
            'billing.index',
            'billing.store',
            'blockedPeriods.index',
            'blockedPeriods.store',
            'blockedPeriods.update',
            'blockedPeriods.destroy',
            // document types
            'document-types.index',
            'document-types.store',
            'document-types.update',
            'document-types.destroy',

            // membership types
            'membership-types.index',
            'membership-types.store',
            'membership-types.update',
            'membership-types.destroy',

            // pricing rules
            'pricing-rules.index',
            'pricing-rules.store',
            'pricing-rules.update',
            'pricing-rules.destroy',
            // interclub package rules
            'interclub-package-rules.index',
            'interclub-package-rules.store',
            'interclub-package-rules.update',
            'interclub-package-rules.destroy',
            // business ads
            'business-ads.index',
            'business-ads.approve',
            'business-ads.reject',
            'business-ads.confirm-payment',
            'business-ads.publish',
            'business-ads.update',
            'business-ads.destroy',
            // business categories
            'business-categories.index',
            'business-categories.store',
            'business-categories.update',
            'business-categories.destroy',
            // members lockers
            'members.lockers.create',
            'members.lockers.store',

            'member-access.index',
            'member-access.store',
            'member-access.destroy',

            'billing-concepts.index',
            'billing-concepts.store',
            'billing-concepts.update',
            'billing-concepts.destroy',

            'payment-methods.index',
            'payment-methods.store',
            'payment-methods.update',
            'payment-methods.destroy',

            'surveys.index',
            'surveys.store',
            'surveys.create',
            'surveys.edit',
            'surveys.update',
            'surveys.destroy',
            'surveys.results',
            'surveys.questions.store',
            'surveys.questions.update',
            'surveys.questions.destroy',
            'surveys.questions.reorder',

        );
        $adminClubRole = Role::updateOrCreate([
            'name' => 'admin_club',
        ], [
            'description' => 'Administrador del club',
            'context_id' => Context::firstOrCreate([
                'value' => 'web',
            ], [
                'name' => 'Web',
                'value' => 'web'
            ])->id
        ]);
        $adminClubRole->syncPermissions($adminClubPermissions);
    }
}
