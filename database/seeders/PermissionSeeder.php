<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Administración
            ['module' => 'Administración', 'name' => 'profile.show', 'description' => 'Ver perfil', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.index', 'description' => 'Ver permisos', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.store', 'description' => 'Crear permisos', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.update', 'description' => 'Actualizar permisos', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.destroy', 'description' => 'Eliminar permisos', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.index', 'description' => 'Ver roles', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.store', 'description' => 'Crear roles', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.update', 'description' => 'Actualizar roles', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.destroy', 'description' => 'Eliminar roles', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.duplicate', 'description' => 'Duplicar roles', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.index', 'description' => 'Ver usuarios', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.store', 'description' => 'Crear usuarios', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.update', 'description' => 'Actualizar usuarios', 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.destroy', 'description' => 'Eliminar usuarios', 'contexts' => ['web']],

            // Clubes
            ['module' => 'Clubes', 'name' => 'clubs.index', 'description' => 'Ver clubes', 'contexts' => ['web']],
            ['module' => 'Clubes', 'name' => 'clubs.store', 'description' => 'Crear clubes', 'contexts' => ['web']],
            ['module' => 'Clubes', 'name' => 'clubs.update', 'description' => 'Actualizar clubes', 'contexts' => ['web']],
            ['module' => 'Clubes', 'name' => 'clubs.destroy', 'description' => 'Eliminar clubes', 'contexts' => ['web']],

            // Amenidades
            ['module' => 'Amenidades', 'name' => 'amenities.index', 'description' => 'Ver amenidades', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenities.store', 'description' => 'Crear amenidades', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenities.update', 'description' => 'Actualizar amenidades', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenities.destroy', 'description' => 'Eliminar amenidades', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.index', 'description' => 'Ver recursos de la amenidad', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.store', 'description' => 'Crear recursos de la amenidad', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.update', 'description' => 'Actualizar recursos de la amenidad', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.destroy', 'description' => 'Eliminar recursos de la amenidad', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.index', 'description' => 'Ver bloqueos', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.store', 'description' => 'Crear bloqueos', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.update', 'description' => 'Actualizar bloqueos', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.destroy', 'description' => 'Eliminar bloqueos', 'contexts' => ['web']],

            // Reservaciones
            ['module' => 'Reservaciones', 'name' => 'reservations.index', 'description' => 'Ver reservaciones', 'contexts' => ['web']],
            ['module' => 'Reservaciones', 'name' => 'reservations.update', 'description' => 'Cancelar reservaciones', 'contexts' => ['web']],

            // Comunicación
            ['module' => 'Comunicación', 'name' => 'announcements.index', 'description' => 'Ver anuncios', 'contexts' => ['web']],
            ['module' => 'Comunicación', 'name' => 'announcements.store', 'description' => 'Crear anuncios', 'contexts' => ['web']],
            ['module' => 'Comunicación', 'name' => 'announcements.update', 'description' => 'Actualizar anuncios', 'contexts' => ['web']],
            ['module' => 'Comunicación', 'name' => 'announcements.destroy', 'description' => 'Eliminar anuncios', 'contexts' => ['web']],

            // Sistema
            ['module' => 'Sistema', 'name' => 'system-variables.index', 'description' => 'Ver variables de sistema', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'system-variables.store', 'description' => 'Crear variables de sistema', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'system-variables.update', 'description' => 'Actualizar variables de sistema', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'system-variables.destroy', 'description' => 'Eliminar variables de sistema', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.index', 'description' => 'Ver reglas de precio', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.store', 'description' => 'Crear reglas de precio', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.update', 'description' => 'Actualizar reglas de precio', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.destroy', 'description' => 'Eliminar reglas de precio', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.index', 'description' => 'Ver paquetes interclub', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.store', 'description' => 'Crear paquetes interclub', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.update', 'description' => 'Actualizar paquetes interclub', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.destroy', 'description' => 'Eliminar paquetes interclub', 'contexts' => ['web']],

            // Tipos de documento
            ['module' => 'Membresías', 'name' => 'document-types.index', 'description' => 'Ver tipos de documento', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'document-types.store', 'description' => 'Crear tipos de documento', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'document-types.update', 'description' => 'Actualizar tipos de documento', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'document-types.destroy', 'description' => 'Eliminar tipos de documento', 'contexts' => ['web']],

            // Tipos de membresía
            ['module' => 'Membresías', 'name' => 'membership-types.index', 'description' => 'Ver tipos de membresía', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'membership-types.store', 'description' => 'Crear tipos de membresía', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'membership-types.update', 'description' => 'Actualizar tipos de membresía', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'membership-types.destroy', 'description' => 'Eliminar tipos de membresía', 'contexts' => ['web']],

            // Membresías
            ['module' => 'Membresías', 'name' => 'members.index', 'description' => 'Ver usuarios del club', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.create', 'description' => 'Crear usuarios del club', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.store', 'description' => 'Guardar usuarios del club', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.edit', 'description' => 'Editar usuarios del club', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.update', 'description' => 'Actualizar usuarios del club', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.destroy', 'description' => 'Eliminar usuarios del club', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.cancel', 'description' => 'Dar de baja una cuenta', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.create', 'description' => 'Crear reservación de casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.store', 'description' => 'Guardar reservación de casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.change', 'description' => 'Cambiar casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.remove', 'description' => 'Cancelar asignación de casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.reserve', 'description' => 'Reservar casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.cancel', 'description' => 'Cancelar reservación de casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'lockers.available', 'description' => 'Ver casilleros disponibles', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'lockers.assigned.by.account', 'description' => 'Ver casilleros asignados a una cuenta', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'lockers.available.for.change', 'description' => 'Ver casilleros disponibles para cambio', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'acts.index', 'description' => 'Ver multas y/o actas', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'acts.store', 'description' => 'Guardar multas y/o actas', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'acts.update', 'description' => 'Actualizar multas y/o actas', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'member-access.index', 'description' => 'Ver acceso de miembros', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'member-access.store', 'description' => 'Asignar acceso a miembros', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'member-access.destroy', 'description' => 'Revocar acceso a miembros', 'contexts' => ['web']],
            // Cobranza
            ['module' => 'Cobranza', 'name' => 'billing.index', 'description' => 'Ver módulo de cobranza', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing.store', 'description' => 'Registrar cobros', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.index', 'description' => 'Ver conceptos de cobro', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.store', 'description' => 'Crear conceptos de cobro', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.update', 'description' => 'Actualizar conceptos de cobro', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.destroy', 'description' => 'Eliminar conceptos de cobro', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'payment-methods.index', 'description' => 'Ver métodos de pago', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'payment-methods.store', 'description' => 'Crear métodos de pago', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'payment-methods.update', 'description' => 'Actualizar métodos de pago', 'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'payment-methods.destroy', 'description' => 'Eliminar métodos de pago', 'contexts' => ['web']],

            // Cortes de caja
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.index', 'description' => 'Ver mis cortes de caja', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.store', 'description' => 'Abrir corte de caja', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.show', 'description' => 'Ver detalle de corte de caja', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.close', 'description' => 'Cerrar corte de caja', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.view-all', 'description' => 'Ver cortes de todos los cajeros', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.index', 'description' => 'Ver cortes globales', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.store', 'description' => 'Crear corte global', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.show', 'description' => 'Ver detalle de corte global', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.close', 'description' => 'Cerrar corte global', 'contexts' => ['web']],

            // Encuestas
            ['module' => 'Encuestas', 'name' => 'surveys.index', 'description' => 'Ver encuestas', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.create', 'description' => 'Crear encuestas', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.store', 'description' => 'Guardar encuestas', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.edit', 'description' => 'Editar encuestas', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.update', 'description' => 'Actualizar encuestas', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.destroy', 'description' => 'Eliminar encuestas', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.results', 'description' => 'Ver resultados de encuesta', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.questions.store', 'description' => 'Crear preguntas de encuesta', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.questions.update', 'description' => 'Actualizar preguntas de encuesta', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.questions.destroy', 'description' => 'Eliminar preguntas de encuesta', 'contexts' => ['web']],
            ['module' => 'Encuestas', 'name' => 'surveys.questions.reorder', 'description' => 'Reordenar preguntas de encuesta', 'contexts' => ['web']],

            // Publicidad
            ['module' => 'Publicidad', 'name' => 'business-ads.index', 'description' => 'Ver publicidad de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-ads.approve', 'description' => 'Aprobar publicidad de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-ads.reject', 'description' => 'Rechazar publicidad de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-ads.confirm-payment', 'description' => 'Confirmar pago de publicidad', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-ads.publish', 'description' => 'Publicar publicidad de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-ads.update', 'description' => 'Actualizar publicidad de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-ads.destroy', 'description' => 'Eliminar publicidad de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-categories.index', 'description' => 'Ver categorías de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-categories.store', 'description' => 'Crear categorías de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-categories.update', 'description' => 'Actualizar categorías de negocios', 'contexts' => ['web']],
            ['module' => 'Publicidad', 'name' => 'business-categories.destroy', 'description' => 'Eliminar categorías de negocios', 'contexts' => ['web']],

            // App Móvil
            ['module' => 'App Móvil', 'name' => 'mobile.reservations.index', 'description' => 'Ver mis reservaciones', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.reservations.store', 'description' => 'Crear reservación', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.reservations.cancel', 'description' => 'Cancelar mis reservaciones', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.reservations.guests.index', 'description' => 'Ver lista de invitados', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.reservations.guests.manage', 'description' => 'Gestionar lista de invitados', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.amenities.index', 'description' => 'Ver amenidades disponibles', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.membership.show', 'description' => 'Ver mi membresía y vigencia', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.billing.show', 'description' => 'Ver estado de cuenta', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.announcements.index', 'description' => 'Ver anuncios del club', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.rules.index', 'description' => 'Ver reglamento del club', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            // Feedback movil
            ['module' => 'App Móvil', 'name' => 'mobile.feedback.index', 'description' => 'Ver mis tickets de quejas y sugerencias', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.feedback.store', 'description' => 'Crear ticket de quejas y sugerencias', 'contexts' => ['mobile_club_1', 'mobile_club_2']],
            ['module' => 'App Móvil', 'name' => 'mobile.feedback.cancel', 'description' => 'Cancelar ticket de quejas y sugerencias', 'contexts' => ['mobile_club_1', 'mobile_club_2']],

            // Quejas y Sugerencias
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-categories.index', 'description' => 'Ver categorías de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-categories.store', 'description' => 'Crear categorías de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-categories.update', 'description' => 'Actualizar categorías de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-categories.destroy', 'description' => 'Eliminar categorías de quejas y sugerencias', 'contexts' => ['web']],
            // Feedback Ticket Types
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-ticket-types.index', 'description' => 'Ver tipos de ticket de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-ticket-types.store', 'description' => 'Crear tipos de ticket de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-ticket-types.update', 'description' => 'Actualizar tipos de ticket de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-ticket-types.destroy', 'description' => 'Eliminar tipos de ticket de quejas y sugerencias', 'contexts' => ['web']],
            // Feedback Statuses
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-statuses.index', 'description' => 'Ver estatus de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-statuses.store', 'description' => 'Crear estatus de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-statuses.update', 'description' => 'Actualizar estatus de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-statuses.destroy', 'description' => 'Eliminar estatus de quejas y sugerencias', 'contexts' => ['web']],
            // Feedback Priorities
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-priorities.index', 'description' => 'Ver prioridades de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-priorities.store', 'description' => 'Crear prioridades de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-priorities.update', 'description' => 'Actualizar prioridades de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-priorities.destroy', 'description' => 'Eliminar prioridades de quejas y sugerencias', 'contexts' => ['web']],

            // Feedback
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback.index', 'description' => 'Ver quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback.store', 'description' => 'Crear quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback.update', 'description' => 'Actualizar quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback.destroy', 'description' => 'Eliminar quejas y sugerencias', 'contexts' => ['web']],

            // Feedback Management
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-management.index', 'description' => 'Ver gestion de casos de quejas y sugerencias', 'contexts' => ['web']],
            ['module' => 'Quejas y Sugerencias', 'name' => 'feedback-management.update', 'description' => 'Gestionar casos de quejas y sugerencias', 'contexts' => ['web']],
        ];
        foreach ($permissions as $permission) {
            $record = Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                    'guard_name' => 'web',
                ]
            );

            $contextIds = array_map(
                fn($ctx) => Context::firstOrCreate(['value' => $ctx], ['name' => $ctx, 'value' => $ctx])->id,
                $permission['contexts']
            );

            $record->contexts()->sync($contextIds);
        }
    }
}
