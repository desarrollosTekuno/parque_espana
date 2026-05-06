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
            ['module' => 'Administración', 'name' => 'profile.show',           'description' => 'Ver perfil',                  'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.index',       'description' => 'Ver permisos',                 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.store',       'description' => 'Crear permisos',               'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.update',      'description' => 'Actualizar permisos',          'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'permissions.destroy',     'description' => 'Eliminar permisos',            'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.index',             'description' => 'Ver roles',                    'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.store',             'description' => 'Crear roles',                  'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.update',            'description' => 'Actualizar roles',             'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.destroy',           'description' => 'Eliminar roles',               'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'roles.duplicate',         'description' => 'Duplicar roles',               'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.index',             'description' => 'Ver usuarios',                 'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.store',             'description' => 'Crear usuarios',               'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.update',            'description' => 'Actualizar usuarios',          'contexts' => ['web']],
            ['module' => 'Administración', 'name' => 'users.destroy',           'description' => 'Eliminar usuarios',            'contexts' => ['web']],

            // Clubes
            ['module' => 'Clubes', 'name' => 'clubs.index',   'description' => 'Ver clubes',        'contexts' => ['web']],
            ['module' => 'Clubes', 'name' => 'clubs.store',   'description' => 'Crear clubes',      'contexts' => ['web']],
            ['module' => 'Clubes', 'name' => 'clubs.update',  'description' => 'Actualizar clubes', 'contexts' => ['web']],
            ['module' => 'Clubes', 'name' => 'clubs.destroy', 'description' => 'Eliminar clubes',   'contexts' => ['web']],

            // Amenidades
            ['module' => 'Amenidades', 'name' => 'amenities.index',          'description' => 'Ver amenidades',                     'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenities.store',          'description' => 'Crear amenidades',                   'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenities.update',         'description' => 'Actualizar amenidades',              'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenities.destroy',        'description' => 'Eliminar amenidades',                'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.index',    'description' => 'Ver recursos de la amenidad',        'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.store',    'description' => 'Crear recursos de la amenidad',      'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.update',   'description' => 'Actualizar recursos de la amenidad', 'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'amenityResource.destroy',  'description' => 'Eliminar recursos de la amenidad',   'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.index',     'description' => 'Ver bloqueos',                       'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.store',     'description' => 'Crear bloqueos',                     'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.update',    'description' => 'Actualizar bloqueos',                'contexts' => ['web']],
            ['module' => 'Amenidades', 'name' => 'blockedPeriods.destroy',   'description' => 'Eliminar bloqueos',                  'contexts' => ['web']],

            // Reservaciones
            ['module' => 'Reservaciones', 'name' => 'reservations.index',  'description' => 'Ver reservaciones',      'contexts' => ['web']],
            ['module' => 'Reservaciones', 'name' => 'reservations.update', 'description' => 'Cancelar reservaciones', 'contexts' => ['web']],

            array('name' => 'guest-lists.index', 'description' => 'Ver listas de invitados', 'contexts' => ['web']),
            array('name' => 'guest-lists.update', 'description' => 'Actualizar estatus de lista de invitados', 'contexts' => ['web']),

            // Comunicación
            ['module' => 'Comunicación', 'name' => 'announcements.index',   'description' => 'Ver anuncios',        'contexts' => ['web']],
            ['module' => 'Comunicación', 'name' => 'announcements.store',   'description' => 'Crear anuncios',      'contexts' => ['web']],
            ['module' => 'Comunicación', 'name' => 'announcements.update',  'description' => 'Actualizar anuncios', 'contexts' => ['web']],
            ['module' => 'Comunicación', 'name' => 'announcements.destroy', 'description' => 'Eliminar anuncios',   'contexts' => ['web']],

            // Sistema
            ['module' => 'Sistema', 'name' => 'system-variables.index',            'description' => 'Ver variables de sistema',        'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'system-variables.store',            'description' => 'Crear variables de sistema',      'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'system-variables.update',           'description' => 'Actualizar variables de sistema', 'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'system-variables.destroy',          'description' => 'Eliminar variables de sistema',   'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.index',               'description' => 'Ver reglas de precio',            'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.store',               'description' => 'Crear reglas de precio',          'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.update',              'description' => 'Actualizar reglas de precio',     'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'pricing-rules.destroy',             'description' => 'Eliminar reglas de precio',       'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.index',     'description' => 'Ver paquetes interclub',          'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.store',     'description' => 'Crear paquetes interclub',        'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.update',    'description' => 'Actualizar paquetes interclub',   'contexts' => ['web']],
            ['module' => 'Sistema', 'name' => 'interclub-package-rules.destroy',   'description' => 'Eliminar paquetes interclub',     'contexts' => ['web']],

            // Membresías
            ['module' => 'Membresías', 'name' => 'members.index',          'description' => 'Ver usuarios del club',            'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.create',         'description' => 'Crear usuarios del club',          'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.store',          'description' => 'Guardar usuarios del club',        'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.edit',           'description' => 'Editar usuarios del club',         'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.update',         'description' => 'Actualizar usuarios del club',     'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.destroy',        'description' => 'Eliminar usuarios del club',       'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.create', 'description' => 'Crear asignación de casillero',   'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'members.lockers.store',  'description' => 'Guardar asignación de casillero', 'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'member-access.index',   'description' => 'Ver acceso de miembros',           'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'member-access.store',   'description' => 'Asignar acceso a miembros',        'contexts' => ['web']],
            ['module' => 'Membresías', 'name' => 'member-access.destroy', 'description' => 'Revocar acceso a miembros',        'contexts' => ['web']],

            // Cobranza
            ['module' => 'Cobranza', 'name' => 'billing.index',              'description' => 'Ver módulo de cobranza',          'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing.store',              'description' => 'Registrar cobros',                'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.index',     'description' => 'Ver conceptos de cobro',          'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.store',     'description' => 'Crear conceptos de cobro',        'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.update',    'description' => 'Actualizar conceptos de cobro',   'contexts' => ['web']],
            ['module' => 'Cobranza', 'name' => 'billing-concepts.destroy',   'description' => 'Eliminar conceptos de cobro',     'contexts' => ['web']],

            // Cortes de caja
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.index',      'description' => 'Ver mis cortes de caja',          'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.store',      'description' => 'Abrir corte de caja',             'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.show',       'description' => 'Ver detalle de corte de caja',    'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.close',      'description' => 'Cerrar corte de caja',            'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'cash-cuts.view-all',   'description' => 'Ver cortes de todos los cajeros', 'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.index', 'description' => 'Ver cortes globales',           'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.store', 'description' => 'Crear corte global',            'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.show',  'description' => 'Ver detalle de corte global',   'contexts' => ['web']],
            ['module' => 'Cortes de caja', 'name' => 'global-cash-cuts.close', 'description' => 'Cerrar corte global',           'contexts' => ['web']],

            // Encuestas
            array('name' => 'surveys.index', 'description' => 'Ver encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.create', 'description' => 'Crear encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.store', 'description' => 'Guardar encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.edit', 'description' => 'Editar encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.update', 'description' => 'Actualizar encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.destroy', 'description' => 'Eliminar encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.results', 'description' => 'Ver resultados de encuesta', 'contexts' => ['web']),

            // Business Ads permissions
            array('name' => 'business-ads.index', 'description' => 'Ver publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.approve', 'description' => 'Aprobar publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.reject', 'description' => 'Rechazar publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.confirm-payment', 'description' => 'Confirmar pago de publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.publish', 'description' => 'Publicar publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.update', 'description' => 'Actualizar publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.destroy', 'description' => 'Eliminar publicidad de negocios', 'contexts' => ['web']),


            // Business Categories permissions
            array('name' => 'business-categories.index', 'description' => 'Ver categorías de negocios', 'contexts' => ['web']),
            array('name' => 'business-categories.store', 'description' => 'Crear categorías de negocios', 'contexts' => ['web']),
            array('name' => 'business-categories.update', 'description' => 'Actualizar categorías de negocios', 'contexts' => ['web']),
            array('name' => 'business-categories.destroy', 'description' => 'Eliminar categorías de negocios', 'contexts' => ['web']),


            // Lockers
            array('name' => 'members.lockers.create', 'description' => 'Crear asignación de casillero', 'contexts' => ['web']),
            array('name' => 'members.lockers.store', 'description' => 'Guardar asignación de casillero', 'contexts' => ['web']),

            //

            array('name' => 'member-access.index', 'description' => 'Ver acceso de miembros', 'contexts' => ['web']),
            array('name' => 'member-access.store', 'description' => 'Asignar acceso a miembros', 'contexts' => ['web']),
            array('name' => 'member-access.destroy', 'description' => 'Revocar acceso a miembros', 'contexts' => ['web']),

            // ============================== Feedback ==============================
            // Feedback Categories
            array('name' => 'feedback-categories.index', 'description' => 'Ver categorías de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-categories.store', 'description' => 'Crear categorías de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-categories.update', 'description' => 'Actualizar categorías de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-categories.destroy', 'description' => 'Eliminar categorías de feedback', 'contexts' => ['web']),

            // Feedback Ticket Types
            array('name' => 'feedback-ticket-types.index', 'description' => 'Ver tipos de ticket de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-ticket-types.store', 'description' => 'Crear tipos de ticket de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-ticket-types.update', 'description' => 'Actualizar tipos de ticket de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-ticket-types.destroy', 'description' => 'Eliminar tipos de ticket de feedback', 'contexts' => ['web']),

            // Feedback Statuses
            array('name' => 'feedback-statuses.index', 'description' => 'Ver estatus de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-statuses.store', 'description' => 'Crear estatus de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-statuses.update', 'description' => 'Actualizar estatus de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-statuses.destroy', 'description' => 'Eliminar estatus de feedback', 'contexts' => ['web']),

            // Feedback Priorities
            array('name' => 'feedback-priorities.index', 'description' => 'Ver prioridades de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-priorities.store', 'description' => 'Crear prioridades de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-priorities.update', 'description' => 'Actualizar prioridades de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-priorities.destroy', 'description' => 'Eliminar prioridades de feedback', 'contexts' => ['web']),

            // Feedback
            array('name' => 'feedback.index', 'description' => 'Ver quejas y sugerencias', 'contexts' => ['web']),
            array('name' => 'feedback.store', 'description' => 'Crear quejas y sugerencias', 'contexts' => ['web']),
            array('name' => 'feedback.update', 'description' => 'Actualizar quejas y sugerencias', 'contexts' => ['web']),
            array('name' => 'feedback.destroy', 'description' => 'Eliminar quejas y sugerencias', 'contexts' => ['web']),

            // Feedback Management
            array('name' => 'feedback-management.index', 'description' => 'Ver gestion de casos de feedback', 'contexts' => ['web']),
            array('name' => 'feedback-management.update', 'description' => 'Gestionar casos de feedback', 'contexts' => ['web']),

            // -------------------------------------------------------
            // App Móvil - Permisos base (aplican a ambos clubs)
            // -------------------------------------------------------

            // Reservaciones
            array('name' => 'mobile.reservations.index', 'description' => 'Ver mis reservaciones', 'contexts' => ['mobile_club_1', 'mobile_club_2']),
            array('name' => 'mobile.reservations.store', 'description' => 'Crear reservación', 'contexts' => ['mobile_club_1', 'mobile_club_2']),
            array('name' => 'mobile.reservations.cancel', 'description' => 'Cancelar mis reservaciones', 'contexts' => ['mobile_club_1', 'mobile_club_2']),
            array('name' => 'mobile.reservations.guests.index', 'description' => 'Ver lista de invitados', 'contexts' => ['mobile_club_1', 'mobile_club_2']),
            array('name' => 'mobile.reservations.guests.manage', 'description' => 'Gestionar lista de invitados', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Amenidades
            array('name' => 'mobile.amenities.index', 'description' => 'Ver amenidades disponibles', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Membresía
            array('name' => 'mobile.membership.show', 'description' => 'Ver mi membresía y vigencia', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Estado de cuenta (solo titular)
            array('name' => 'mobile.billing.show', 'description' => 'Ver estado de cuenta', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Feedback movil
            array('name' => 'mobile.feedback.index', 'description' => 'Ver mis tickets de feedback', 'contexts' => ['mobile_club_1', 'mobile_club_2']),
            array('name' => 'mobile.feedback.store', 'description' => 'Crear ticket de feedback', 'contexts' => ['mobile_club_1', 'mobile_club_2']),
            array('name' => 'mobile.feedback.cancel', 'description' => 'Cancelar ticket de feedback', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Anuncios
            array('name' => 'mobile.announcements.index', 'description' => 'Ver anuncios del club', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Reglas del club
            array('name' => 'mobile.rules.index', 'description' => 'Ver reglamento del club', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

        );
        foreach ($permissions as $permission) {
            $record = Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'description' => $permission['description'],
                    'module'       => $permission['module'],
                    'guard_name'   => 'web',
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
