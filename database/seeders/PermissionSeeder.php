<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = array(
            array('name' => 'profile.show', 'description' => 'Ver perfil', 'contexts' => ['web']),
            array('name' => 'permissions.index', 'description' => 'Ver permisos', 'contexts' => ['web']),
            array('name' => 'permissions.store', 'description' => 'Crear permisos', 'contexts' => ['web']),
            array('name' => 'permissions.update', 'description' => 'Actualizar permisos', 'contexts' => ['web']),
            array('name' => 'permissions.destroy', 'description' => 'Eliminar permisos', 'contexts' => ['web']),
            array('name' => 'roles.index', 'description' => 'Ver roles', 'contexts' => ['web']),
            array('name' => 'roles.store', 'description' => 'Crear roles', 'contexts' => ['web']),
            array('name' => 'roles.update', 'description' => 'Actualizar roles', 'contexts' => ['web']),
            array('name' => 'roles.destroy', 'description' => 'Eliminar roles', 'contexts' => ['web']),
            array('name' => 'roles.duplicate', 'description' => 'Duplicar roles', 'contexts' => ['web']),
            // Users
            array('name' => 'users.index', 'description' => 'Ver usuarios', 'contexts' => ['web']),
            array('name' => 'users.store', 'description' => 'Crear usuarios', 'contexts' => ['web']),
            array('name' => 'users.update', 'description' => 'Actualizar usuarios', 'contexts' => ['web']),
            array('name' => 'users.destroy', 'description' => 'Eliminar usuarios', 'contexts' => ['web']),
            // Clubs
            array('name' => 'clubs.index', 'description' => 'Ver clubes', 'contexts' => ['web']),
            array('name' => 'clubs.store', 'description' => 'Crear clubes', 'contexts' => ['web']),
            array('name' => 'clubs.update', 'description' => 'Actualizar clubes', 'contexts' => ['web']),
            array('name' => 'clubs.destroy', 'description' => 'Eliminar clubes', 'contexts' => ['web']),
            // Amenidades
            array('name' => 'amenities.index', 'description' => 'Ver amenidades', 'contexts' => ['web']),
            array('name' => 'amenities.store', 'description' => 'Crear amenidades', 'contexts' => ['web']),
            array('name' => 'amenities.update', 'description' => 'Actualizar amenidades', 'contexts' => ['web']),
            array('name' => 'amenities.destroy', 'description' => 'Eliminar amenidades'),
            // Bloqueos
            array('name' => 'blockedPeriods.index', 'description' => 'Ver bloqueos', 'contexts' => ['web']),
            array('name' => 'blockedPeriods.store', 'description' => 'Crear bloqueos', 'contexts' => ['web']),
            array('name' => 'blockedPeriods.update', 'description' => 'Actualizar bloqueos', 'contexts' => ['web']),
            array('name' => 'blockedPeriods.destroy', 'description' => 'Eliminar bloqueos', 'contexts' => ['web']),
            // Reservaciones
            array('name' => 'reservations.index', 'description' => 'Ver reservaciones', 'contexts' => ['web']),
            array('name' => 'reservations.update', 'description' => 'Cancelar reservaciones', 'contexts' => ['web']),
            // Listas de invitados
            array('name' => 'guest-lists.index', 'description' => 'Ver listas de invitados', 'contexts' => ['web']),
            array('name' => 'guest-lists.update', 'description' => 'Actualizar estatus de lista de invitados', 'contexts' => ['web']),
            array('name' => 'guest-list-variables.index', 'description' => 'Ver variables de listas de invitados', 'contexts' => ['web']),
            array('name' => 'guest-list-variables.store', 'description' => 'Crear variables de listas de invitados', 'contexts' => ['web']),
            array('name' => 'guest-list-variables.update', 'description' => 'Actualizar variables de listas de invitados', 'contexts' => ['web']),
            array('name' => 'guest-list-variables.destroy', 'description' => 'Eliminar variables de listas de invitados', 'contexts' => ['web']),

            // Anuncios
            array('name' => 'announcements.index', 'description' => 'Ver anuncios', 'contexts' => ['web']),
            array('name' => 'announcements.store', 'description' => 'Crear anuncios', 'contexts' => ['web']),
            array('name' => 'announcements.update', 'description' => 'Actualizar anuncios', 'contexts' => ['web']),
            array('name' => 'announcements.destroy', 'description' => 'Eliminar anuncios'),
            // Variables de sistema
            array('name' => 'system-variables.index', 'description' => 'Ver variables de sistema', 'contexts' => ['web']),
            array('name' => 'system-variables.store', 'description' => 'Crear variables de sistema', 'contexts' => ['web']),
            array('name' => 'system-variables.update', 'description' => 'Actualizar variables de sistema', 'contexts' => ['web']),
            array('name' => 'system-variables.destroy', 'description' => 'Eliminar variables de sistema', 'contexts' => ['web']),
            array('name' => 'pricing-rules.index', 'description' => 'Ver reglas de precio', 'contexts' => ['web']),
            array('name' => 'pricing-rules.store', 'description' => 'Crear reglas de precio', 'contexts' => ['web']),
            array('name' => 'pricing-rules.update', 'description' => 'Actualizar reglas de precio', 'contexts' => ['web']),
            array('name' => 'pricing-rules.destroy', 'description' => 'Eliminar reglas de precio', 'contexts' => ['web']),
            array('name' => 'interclub-package-rules.index', 'description' => 'Ver paquetes interclub', 'contexts' => ['web']),
            array('name' => 'interclub-package-rules.store', 'description' => 'Crear paquetes interclub', 'contexts' => ['web']),
            array('name' => 'interclub-package-rules.update', 'description' => 'Actualizar paquetes interclub', 'contexts' => ['web']),
            array('name' => 'interclub-package-rules.destroy', 'description' => 'Eliminar paquetes interclub', 'contexts' => ['web']),

            array('name' => 'amenityResource.index', 'description' => 'Ver recursos de la amenidad', 'contexts' => ['web']),
            array('name' => 'amenityResource.store', 'description' => 'Crear recursos de la amenidad', 'contexts' => ['web']),
            array('name' => 'amenityResource.update', 'description' => 'Actualizar recursos de la amenidad', 'contexts' => ['web']),
            array('name' => 'amenityResource.destroy', 'description' => 'Eliminar recursos de la amenidad', 'contexts' => ['web']),
            // store, update and destroy to amenityResource
            // members permissions
            array('name' => 'members.index', 'description' => 'Ver socios del club', 'contexts' => ['web']),
            array('name' => 'members.create', 'description' => 'Crear socios del club', 'contexts' => ['web']),
            array('name' => 'members.store', 'description' => 'Guardar socios del club', 'contexts' => ['web']),
            array('name' => 'members.edit', 'description' => 'Editar socios del club', 'contexts' => ['web']),
            array('name' => 'members.update', 'description' => 'Actualizar socios del club', 'contexts' => ['web']),
            array('name' => 'members.destroy', 'description' => 'Eliminar socios del club', 'contexts' => ['web']),
            array('name' => 'billing.index', 'description' => 'Ver modulo de cobranza', 'contexts' => ['web']),
            array('name' => 'billing.store', 'description' => 'Registrar cobros', 'contexts' => ['web']),
            array('name' => 'billing-concepts.index', 'description' => 'Ver conceptos de cobro', 'contexts' => ['web']),
            array('name' => 'billing-concepts.store', 'description' => 'Crear conceptos de cobro', 'contexts' => ['web']),
            array('name' => 'billing-concepts.update', 'description' => 'Actualizar conceptos de cobro', 'contexts' => ['web']),
            array('name' => 'billing-concepts.destroy', 'description' => 'Eliminar conceptos de cobro', 'contexts' => ['web']),

            // Business Ads permissions
            array('name' => 'business-ads.index', 'description' => 'Ver publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.approve', 'description' => 'Aprobar publicidad de negocios', 'contexts' => ['web']),
            array('name' => 'business-ads.reject', 'description' => 'Rechazar publicidad de negocios', 'contexts' => ['web']),
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

            // Surveys
            array('name' => 'surveys.index', 'description' => 'Ver encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.store', 'description' => 'Crear encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.update', 'description' => 'Actualizar encuestas', 'contexts' => ['web']),
            array('name' => 'surveys.destroy', 'description' => 'Eliminar encuestas', 'contexts' => ['web']),

            // Lockers
            array('name' => 'members.lockers.create', 'description' => 'Crear asignación de casillero', 'contexts' => ['web']),
            array('name' => 'members.lockers.store', 'description' => 'Guardar asignación de casillero', 'contexts' => ['web']),

            //

            array('name' => 'member-access.index', 'description' => 'Ver acceso de miembros', 'contexts' => ['web']),
            array('name' => 'member-access.store', 'description' => 'Asignar acceso a miembros', 'contexts' => ['web']),
            array('name' => 'member-access.destroy', 'description' => 'Revocar acceso a miembros', 'contexts' => ['web']),


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

            // Anuncios
            array('name' => 'mobile.announcements.index', 'description' => 'Ver anuncios del club', 'contexts' => ['mobile_club_1', 'mobile_club_2']),

            // Reglas del club
            array('name' => 'mobile.rules.index',         'description' => 'Ver reglamento del club',     'contexts' => ['mobile_club_1', 'mobile_club_2']),

        );
        foreach ($permissions as $permission) {
            $createPermission = Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description'], 'guard_name' => 'web']
            );
            if (isset($permission['contexts'])) {
                $createPermission->contexts()->sync(
                    array_map(function ($context) {
                        return Context::firstOrCreate([
                            'value' => $context
                        ], ['name' => $context, 'value' => $context])->id;
                    }, $permission['contexts'])
                );
            } else {
                // Sync with web context by default
                $createPermission->contexts()->sync([
                    Context::firstOrCreate(['value' => 'web'], ['name' => 'Web', 'value' => 'web'])->id
                ]);
            }
        }
    }
}
