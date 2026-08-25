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

            // amenidades
            'amenities.index',
            'amenities.store',
            'amenities.update',
            'amenities.destroy',
            'amenityResource.index',
            'amenityResource.store',
            'amenityResource.update',
            'amenityResource.destroy',
            'amenityResource.calendar',
            'amenityResource.generateQr',
            'amenityResource.downloadQr',
            'amenityResource.downloadQrPdf',
            'amenitySchedule.store',
            'blockedPeriods.index',
            'blockedPeriods.store',
            'blockedPeriods.update',
            'blockedPeriods.destroy',

            // clases
            'specialties.index',
            'specialties.store',
            'specialties.update',
            'specialties.destroy',
            'coaches.index',
            'coaches.store',
            'coaches.update',
            'coaches.destroy',
            'classSchedules.index',
            'classSchedules.store',
            'classSchedules.update',
            'classSchedules.destroy',

            // reservaciones
            'reservations.index',
            'reservations.store',
            'reservations.update',
            'reservations.cancel',
            'reservations.calendar',

            // comunicación
            'announcements.index',
            'announcements.store',
            'announcements.update',
            'announcements.destroy',
            'announcements.storeGallery',
            'announcements.getGallery',
            'announcements.destroyGalleryImage',

            // correo
            'email-configs.index',
            'email-configs.store',
            'email-configs.update',
            'email-configs.destroy',
            'email-notifications.index',
            'email-notifications.store',
            'email-notifications.update',
            'email-notifications.destroy',
            'notifications.index',
            'notifications.store',
            'notifications.update',
            'notifications.destroy',

            // sistema
            'system-variables.index',
            'system-variables.store',
            'system-variables.update',
            'system-variables.destroy',
            'app-variables.index',
            'app-variables.store',
            'app-variables.update',
            'app-variables.destroy',
            'pricing-rules.index',
            'pricing-rules.store',
            'pricing-rules.update',
            'pricing-rules.destroy',
            'interclub-package-rules.index',
            'interclub-package-rules.store',
            'interclub-package-rules.update',
            'interclub-package-rules.destroy',
            'fee-schedules.index',
            'fee-schedules.store',
            'fee-schedules.annual-discount-rules',

            // tipos de documento y de membresía
            'document-types.index',
            'document-types.store',
            'document-types.update',
            'document-types.destroy',
            'membership-types.index',
            'membership-types.store',
            'membership-types.update',
            'membership-types.destroy',

            // pestañas de gestión de membresías
            'accounts.view',
            'members.view',
            'documents.view',
            'absences.view',
            'clinical-history.view',
            'history.view',
            'lockers-history.view',
            'billing.view',
            'billing.update',

            // membresías / integrantes
            'members.index',
            'members.create',
            'members.store',
            'members.edit',
            'members.update',
            'members.destroy',
            'members.cancel',
            'members.reactivate',
            'members.cancellations.index',
            'members.age-transitions.index',
            'members.age-transitions.promote',
            'members.age-transitions.dismiss',
            'members.documents.store',
            'members.cancel.create',
            'members.additional-membership.create',
            'acts.index',
            'acts.store',
            'acts.update',
            'billing.index',
            'reports.index',
            'billing.store',
            'collections.index',
            'collections.store',
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
            // 'business-ads.confirm-payment',
            // 'business-ads.publish',
            // 'business-ads.update',
            // 'business-ads.destroy',
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
            'member-access.reset-password',
            'member-access.destroy',

            // casilleros
            'members.lockers.create',
            'members.lockers.store',
            'members.lockers.change',
            'members.lockers.remove',
            'members.lockers.reserve',
            'members.lockers.history',
            'lockers.available',
            'lockers.assigned.by.account',
            'lockers.available.for.change',

            // cobranza
            'billing.index',
            'reports.index',
            'billing.charges.index',
            'billing.store',
            'billing.payments.non-cash-cut',
            'billing-concepts.index',
            'billing-concepts.store',
            'billing-concepts.update',
            'billing-concepts.destroy',
            'payment-methods.index',
            'payment-methods.store',
            'payment-methods.update',
            'payment-methods.destroy',
            'tickets.index',
            'payments.cancel',

            // cortes de caja
            'cash-cuts.index',
            'cash-cuts.store',
            'cash-cuts.show',
            'cash-cuts.close',
            'cash-cuts.view-all',
            'global-cash-cuts.index',
            'global-cash-cuts.store',
            'global-cash-cuts.show',
            'global-cash-cuts.close',

            // encuestas
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

            // publicidad
            'business-ads.index',
            'business-ads.approve',
            'business-ads.reject',
            'physical-ads.index',
            'physical-ads.store',
            'physical-ad-sizes.index',
            'physical-ad-sizes.store',
            'physical-ad-sizes.update',
            'physical-ad-sizes.destroy',
            'business-categories.index',
            'business-categories.store',
            'business-categories.update',
            'business-categories.destroy',

            // cafetería
            'cafeteria-visits.index',
            'cafeteria-visits.store',
            'cafeteria-visits.checkout',

            // quejas y sugerencias
            'feedback-categories.index',
            'feedback-categories.store',
            'feedback-categories.update',
            'feedback-categories.destroy',
            'feedback-ticket-types.index',
            'feedback-ticket-types.store',
            'feedback-ticket-types.update',
            'feedback-ticket-types.destroy',
            'feedback-statuses.index',
            'feedback-statuses.store',
            'feedback-statuses.update',
            'feedback-statuses.destroy',
            'feedback-priorities.index',
            'feedback-priorities.store',
            'feedback-priorities.update',
            'feedback-priorities.destroy',
            'feedback.index',
            'feedback.store',
            'feedback.update',
            'feedback.destroy',
            'feedback-management.index',
            'feedback-management.update',

            // listas de invitados
            'guest-lists.index',
            'guest-lists.update',
            'guest-list-variables.index',
            'guest-list-variables.store',
            'guest-list-variables.update',
            'guest-list-variables.destroy',
            'guest-list-payments.index',
            'guest-list-payments.store',
            'guest-list-payments.update',
            'guest-list-payments.destroy',

            // pases por día
            'day-passes.index',
            'day-passes.store',
            'day-passes.incidents.index',
            'day-passes.incidents.store',

            // página web
            'website-content.index',
            'website-content.store',
            'website-content.destroy',
            'website-contacts.index',
            // files
            'files.index',
            'files.store',
            'files.update',
            'files.destroy',
            'files.club-file.upload',
            'files.club-file.destroy',
            'files.variables',

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
