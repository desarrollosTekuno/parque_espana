<?php

namespace App\Services\Access;

use App\Models\Devices\Command;
use App\Models\Devices\DailyPassCard;
use App\Models\Devices\Device;
use App\Models\Memberships\MembershipAccountMember;
use Carbon\Carbon;

class AccessProvisioningService
{
    public function __construct(
        private CommandPayloadBuilder $payloadBuilder
    ) {}

    /**
     * Aprovisiona el acceso (usuario + tarjeta) para un integrante de una cuenta.
     * Crea los comandos correspondientes (create_user, create_card) por cada
     * dispositivo activo del club de la cuenta.
     *
     * @param  MembershipAccountMember  $integrante
     * @param  mixed  $cuenta  Registro de memberships.accounts
     */
    public function provision(MembershipAccountMember $integrante, $cuenta): void
    {
        if ($integrante->access_code) {
            return;
        }

        $member = $integrante->member;

        if (!$member) {
            throw new \RuntimeException("No se encontró el member relacionado (member_id: {$integrante->member_id})");
        }

        // 1. Dispositivos activos del club de la cuenta
        $devices = Device::where('club_id', $cuenta->club_id)
            ->where('status', 'active')
            ->get();

        if ($devices->isEmpty()) {
            throw new \RuntimeException("No hay dispositivos activos para el club_id {$cuenta->club_id}");
        }

        // 2. Número de tarjeta único (si el integrante aún no tiene uno)
        $cardNo = $this->generateUniqueCardNumber();
        $validTo = Carbon::now()->addYears(10)->format('Y-m-d H:i:s');

        $integrante->access_code = $cardNo;
        $integrante->access_valid_until = $validTo;
        $integrante->save();


        // 3. Nombre completo
        $fullName = trim("{$member->first_name} {$member->last_name} {$member->second_last_name}");
        $isActive = $integrante->access_status === 'active';

        // 4. Vigencia (no permanente, 10 años a partir de ahora)
        $validFrom = Carbon::now()->format('Y-m-d H:i:s');

        // 5. Un create_user y un create_card por cada dispositivo activo del club
        foreach ($devices as $device) {
            $this->createCommand('create_user', $integrante->id, $device, [
                'users' => [[
                    'employee_id'  => (string) $member->id,
                    'name'         => $fullName,
                    'user_type'    => 'normal',
                    'is_active'    => $isActive,
                    'is_permanent' => false,
                    'valid_from'   => $validFrom,
                    'valid_to'     => $validTo,
                ]],
            ]);

            $this->createCommand('create_card', $integrante->id, $device, [
                'cards' => [[
                    'employee_id' => (string) $member->id,
                    'card_no'     => $cardNo,
                ]],
            ]);
        }
    }

    /**
     * Genera un número de tarjeta aleatorio de 10 dígitos, garantizando que
     * no exista ya en memberships.account_members.access_code.
     */
    public function generateUniqueCardNumber(): string
    {
        do {
            $candidate = (string) random_int(1000000000, 9999999999);
            $exists = MembershipAccountMember::where('access_code', $candidate)->exists()
                || DailyPassCard::where('card_no', $candidate)->where('status', 'active')->exists();
        } while ($exists);

        return $candidate;
    }

    public function createCommand(string $action, ?int $accountMemberId, Device $device, array $rawData): void
    {
        Command::create([
            'action'            => $action,
            'status'            => 'pending',
            'attempts'          => 0,
            'device_id'         => $device->id,
            'account_member_id' => $accountMemberId,
            'data'              => $this->payloadBuilder->build($action, $rawData),
        ]);
    }

    /**
    * Actualiza los datos del usuario (nombre, estatus) en los dispositivos.
    * Usa el access_valid_until ya guardado, sin recalcular fechas.
    */
    public function updateUserInfo(MembershipAccountMember $integrante, $cuenta): void
    {
        $member = $integrante->member;

        if (!$member) {
            throw new \RuntimeException("No se encontró el member relacionado (member_id: {$integrante->member_id})");
        }

        if (!$integrante->access_valid_until) {
            throw new \RuntimeException("El integrante {$integrante->id} no tiene access_valid_until definido");
        }

        $devices = Device::where('club_id', $cuenta->club_id)
            ->where('status', 'active')
            ->get();

        if ($devices->isEmpty()) {
            throw new \RuntimeException("No hay dispositivos activos para el club_id {$cuenta->club_id}");
        }

        $fullName = trim("{$member->first_name} {$member->last_name} {$member->second_last_name}");
        $isActive = $integrante->access_status === 'active';
        $validFrom = Carbon::now()->format('Y-m-d H:i:s');
        $validTo = Carbon::parse($integrante->access_valid_until)->format('Y-m-d H:i:s');

        foreach ($devices as $device) {
            $this->createCommand('update_user', $integrante->id, $device, [
                'users' => [[
                    'employee_id'  => (string) $member->id,
                    'name'         => $fullName,
                    'user_type'    => 'normal',
                    'is_active'    => $isActive,
                    'is_permanent' => false,
                    'valid_from'   => $validFrom,
                    'valid_to'     => $validTo,
                ]],
            ]);
        }
    }

    /**
    * Reasigna un número de tarjeta nuevo a un integrante que YA tiene acceso
    * dado de alta (usuario ya existe). Solo crea el comando update_card.
    */
    public function reassignCard(MembershipAccountMember $integrante, $cuenta): void
    {
        $member = $integrante->member;

        if (!$member) {
            throw new \RuntimeException("No se encontró el member relacionado (member_id: {$integrante->member_id})");
        }

        $devices = Device::where('club_id', $cuenta->club_id)
            ->where('status', 'active')
            ->get();

        if ($devices->isEmpty()) {
            throw new \RuntimeException("No hay dispositivos activos para el club_id {$cuenta->club_id}");
        }

        $newCardNo = $this->generateUniqueCardNumber();
        $integrante->access_code = $newCardNo;
        $integrante->save();

        foreach ($devices as $device) {
            $this->createCommand('update_card', $integrante->id, $device, [
                'cards' => [[
                    'employee_id' => (string) $member->id,
                    'card_no'     => $newCardNo,
                ]],
            ]);
        }
    }

    /**
     * Revoca el acceso de un integrante (baja de cuenta). Crea el comando
     * delete_user por cada dispositivo activo del club, y limpia el
     * access_code para dejarlo disponible de nuevo.
     */
    public function revokeAccess(MembershipAccountMember $integrante, $cuenta): void
    {
        $member = $integrante->member;

        if (!$member) {
            throw new \RuntimeException("No se encontró el member relacionado (member_id: {$integrante->member_id})");
        }

        // Si nunca tuvo acceso dado de alta, no hay nada que revocar
        if (!$integrante->access_code) {
            return;
        }

        $devices = Device::where('club_id', $cuenta->club_id)
            ->where('status', 'active')
            ->get();

        foreach ($devices as $device) {
            $this->createCommand('delete_user', $integrante->id, $device, [
                'users' => [[
                    'employee_id' => (string) $member->id,
                ]],
            ]);
        }

        $integrante->access_code = null;
        $integrante->access_valid_until = null;
        $integrante->save();
    }
}

