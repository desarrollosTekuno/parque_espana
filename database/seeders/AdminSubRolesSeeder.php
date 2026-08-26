<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminSubRolesSeeder extends Seeder
{
    /**
     * Crea (o actualiza) los roles administrativos especializados del club
     * (subconjuntos de admin_club) y sincroniza sus permisos. Seguro de correr
     * múltiples veces.
     */
    public function run(): void
    {
        $webContextId = Context::firstOrCreate([
            'value' => 'web',
        ], [
            'name' => 'Web',
            'value' => 'web',
        ])->id;

        $roles = [
            'Cobranza' => [
                'description' => 'Rol para persona encargada de la cobranza',
                'permissions' => [
                    'profile.show',
                    'reports.index',
                    'tickets.index',
                    'cash-cuts.index',
                    'cash-cuts.store',
                    'cash-cuts.show',
                    'cash-cuts.close',
                    'day-passes.index',
                    'day-passes.store',
                    'day-passes.incidents.index',
                    'day-passes.incidents.store',
                    'business-ads.index',
                ],
            ],
            'Amenidades' => [
                'description' => 'Rol relacionado a amenidades y reservaciones',
                'permissions' => [
                    'profile.show',
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
                    'coaches.index',
                    'coaches.store',
                    'coaches.update',
                    'coaches.destroy',
                    'reservations.index',
                    'reservations.store',
                    'reservations.update',
                    'reservations.cancel',
                    'reservations.calendar',
                    'system-variables.index',
                    'system-variables.store',
                    'system-variables.update',
                    'system-variables.destroy',
                ],
            ],
            'Quejas y Encuestas' => [
                'description' => 'Rol para gestionar quejas y sugerencias y encuestas',
                'permissions' => [
                    'profile.show',
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
                ],
            ],
            'Membresias' => [
                'description' => null,
                'permissions' => [
                    'accounts.view',
                    'members.view',
                    'documents.view',
                    'absences.view',
                    'clinical-history.view',
                    'history.view',
                    'lockers-history.view',
                    'document-types.index',
                    'document-types.store',
                    'document-types.update',
                    'document-types.destroy',
                    'membership-types.index',
                    'membership-types.store',
                    'membership-types.update',
                    'membership-types.destroy',
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
                    'member-access.index',
                    'member-access.store',
                    'member-access.reset-password',
                    'member-access.destroy',
                    'members.lockers.create',
                    'members.lockers.store',
                    'members.lockers.change',
                    'members.lockers.remove',
                    'members.lockers.reserve',
                    'members.lockers.history',
                    'lockers.available',
                    'lockers.assigned.by.account',
                    'lockers.available.for.change',
                ],
            ],
            'Cobranza Admin' => [
                'description' => null,
                'permissions' => [
                    'profile.show',
                    'reports.index',
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
                    'cash-cuts.index',
                    'cash-cuts.store',
                    'cash-cuts.show',
                    'cash-cuts.close',
                    'cash-cuts.view-all',
                    'global-cash-cuts.index',
                    'global-cash-cuts.store',
                    'global-cash-cuts.show',
                    'global-cash-cuts.close',
                ],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $data['description'],
                    'context_id' => $webContextId,
                ]
            );
            $role->syncPermissions($data['permissions']);
        }
    }
}
