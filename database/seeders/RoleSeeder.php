<?php

namespace Database\Seeders;

use App\Models\Context;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Crea (o actualiza) los roles de la app móvil para cada club
     * y les asigna sus permisos base. Es seguro correrlo múltiples veces.
     */
    public function run(): void
    {
        $mobileContexts = ['mobile_club_1', 'mobile_club_2'];

        // Permisos que recibe el usuario titular
        $titularPermissions = [
            'mobile.reservations.index',
            'mobile.reservations.store',
            'mobile.reservations.cancel',
            'mobile.reservations.guests.index',
            'mobile.reservations.guests.manage',
            'mobile.amenities.index',
            'mobile.membership.show',
            'mobile.billing.show',       // solo el titular ve el estado de cuenta
            'mobile.announcements.index',
            'mobile.rules.index',
        ];

        // Permisos que recibe el usuario dependiente
        $dependientePermissions = [
            'mobile.reservations.index',
            'mobile.reservations.store',
            'mobile.reservations.cancel',
            'mobile.reservations.guests.index',
            'mobile.reservations.guests.manage',
            'mobile.amenities.index',
            'mobile.membership.show',
            'mobile.announcements.index',
            'mobile.rules.index',
            // mobile.billing.show NO incluido — el dependiente no ve el estado de cuenta
        ];

        foreach ($mobileContexts as $contextValue) {
            $context = Context::where('value', $contextValue)->first();

            if (!$context) {
                $this->command->warn("Contexto '{$contextValue}' no encontrado. Asegúrate de ejecutar ContextSeeder primero.");
                continue;
            }

            // Rol: socio_titular
            $titular = Role::updateOrCreate(
                [
                    'name'       => 'socio_titular',
                    'guard_name' => 'web',
                    'context_id' => $context->id,
                ],
                // No hay campos adicionales que actualizar por ahora
            );
            $titular->syncPermissions(
                Permission::whereIn('name', $titularPermissions)->where('guard_name', 'web')->get()
            );

            // Rol: socio_dependiente
            $dependiente = Role::updateOrCreate(
                [
                    'name'       => 'socio_dependiente',
                    'guard_name' => 'web',
                    'context_id' => $context->id,
                ],
            );
            $dependiente->syncPermissions(
                Permission::whereIn('name', $dependientePermissions)->where('guard_name', 'web')->get()
            );

            $this->command->info("Roles creados/actualizados para contexto: {$contextValue}");
        }

        // Limpiar el cache de permisos de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
