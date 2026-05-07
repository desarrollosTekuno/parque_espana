<?php

namespace App\Services;

use App\Models\Context;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Role;
use App\Models\User;

class MemberAccessService
{
    /**
     * Mapeo de código de club al valor de contexto móvil correspondiente.
     */
    private const CLUB_CONTEXT_MAP = [
        'PE1' => 'mobile_club_1',
        'PE2' => 'mobile_club_2',
    ];

    /**
     * Sincroniza los roles móviles del usuario vinculado al miembro.
     * Agrega los roles que le correspondan y elimina los que ya no apliquen.
     * Si el miembro no tiene usuario, no hace nada.
     */
    public function syncMobileRoles(Member $member): void
    {
        $user = $member->user;

        if (!$user) {
            return;
        }

        // Roles móviles que debería tener según sus membresías actuales
        $expectedRoles = $this->resolveExpectedRoles($member);

        // Roles móviles que tiene actualmente
        $currentMobileRoles = $user->roles()
            ->whereNotNull('context_id')
            ->whereHas('context', fn ($q) => $q->where('value', 'like', 'mobile_%'))
            ->get();

        // Quitar roles móviles que ya no aplican
        foreach ($currentMobileRoles as $currentRole) {
            $stillNeeded = $expectedRoles->contains(fn ($r) => $r->id === $currentRole->id);
            if (!$stillNeeded) {
                $user->removeRole($currentRole);
            }
        }

        // Agregar roles nuevos que aún no tiene
        foreach ($expectedRoles as $role) {
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        // Sincronizar user_clubs según los clubs activos
        $this->syncUserClubs($user, $member);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Resuelve la colección de roles que el miembro debería tener
     * basándose en sus cuentas de membresía activas.
     */
    private function resolveExpectedRoles(Member $member): \Illuminate\Support\Collection
    {
        $accountMemberships = MembershipAccountMember::with('membershipAccount.club')
            ->where('member_id', $member->id)
            ->get();

        $roles = collect();

        foreach ($accountMemberships as $accountMember) {
            $club = $accountMember->membershipAccount?->club;

            if (!$club) {
                continue;
            }

            $contextValue = self::CLUB_CONTEXT_MAP[$club->code] ?? null;

            if (!$contextValue) {
                continue;
            }

            $context = Context::where('value', $contextValue)->first();

            if (!$context) {
                continue;
            }

            $roleName = $accountMember->is_primary_holder
                ? 'socio_titular'
                : 'socio_dependiente';

            $role = Role::where('name', $roleName)
                ->where('context_id', $context->id)
                ->first();

            if ($role) {
                $roles->push($role);
            }
        }

        return $roles;
    }

    /**
     * Mantiene user_clubs alineado con los clubs donde el miembro tiene membresía.
     */
    private function syncUserClubs(User $user, Member $member): void
    {
        $clubIds = MembershipAccountMember::with('membershipAccount')
            ->where('member_id', $member->id)
            ->get()
            ->pluck('membershipAccount.club_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $user->clubs()->sync($clubIds);
    }
}
