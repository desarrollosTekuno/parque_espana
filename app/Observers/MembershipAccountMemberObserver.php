<?php

namespace App\Observers;

use App\Models\Memberships\MembershipAccountMember;
use App\Services\MemberAccessService;

class MembershipAccountMemberObserver
{
    public function __construct(private MemberAccessService $accessService)
    {
    }

    /**
     * Se dispara cuando se agrega un miembro a una cuenta de membresía.
     * Si el miembro ya tiene usuario, sincroniza sus roles móviles.
     */
    public function created(MembershipAccountMember $accountMember): void
    {
        $this->sync($accountMember);
    }

    /**
     * Se dispara cuando cambia is_primary_holder u otro campo relevante.
     * Actualiza el rol (titular ↔ dependiente) si es necesario.
     */
    public function updated(MembershipAccountMember $accountMember): void
    {
        if ($accountMember->wasChanged(['is_primary_holder', 'membership_account_id'])) {
            $this->sync($accountMember);
        }
    }

    /**
     * Se dispara cuando se elimina un miembro de una cuenta.
     * Quita el rol del club correspondiente si ya no tiene membresía en él.
     */
    public function deleted(MembershipAccountMember $accountMember): void
    {
        $this->sync($accountMember);
    }

    private function sync(MembershipAccountMember $accountMember): void
    {
        $member = $accountMember->member;

        if (!$member || !$member->user_id) {
            return;
        }

        $this->accessService->syncMobileRoles($member);
    }
}
