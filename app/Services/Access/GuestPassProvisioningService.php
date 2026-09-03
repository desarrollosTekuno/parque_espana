<?php

namespace App\Services\Access;

use App\Models\Devices\Command;
use App\Models\Devices\DailyPassCard;
use App\Models\Devices\Device;
use App\Models\Devices\GuestUser;
use Carbon\Carbon;

class GuestPassProvisioningService
{
    public function __construct(
        private AccessProvisioningService $accessProvisioningService
    )
    { }

    /**
     * Da de alta el acceso de un pase diario en todos los dispositivos
     * activos del club. Genera UNA tarjeta física (mismo card_no en todos),
     * usando un usuario invitado con espacio disponible por dispositivo
     * (o creando uno nuevo si no hay).
     *
     * @param  int  $clubId
     * @param  Carbon  $validUntil  hasta cuándo es válida esta tarjeta
     * @param  int|null  $accountMemberId  nullable, si el pase viene ligado a un socio
     * @param  int|null  $chargeId  nullable, referencia al cobro que generó este pase
     * @return string  el card_no generado
    */

    public function provisionDayPass(int $clubId, Carbon $validUntil, ?int $accountMemberId = null, ?int $chargeId = null): string
    {
        $devices = Device::where('club_id', $clubId)
            ->where('status', 'active')
            ->get();


        if ($devices->isEmpty()) {
            throw new \RuntimeException("No hay dispositivos activos para el club_id {$clubId}");
        }

        $cardNo = $this->accessProvisioningService->generateUniqueCardNumber();

        foreach ($devices as $device) {
            $guestUser = $this->findOrCreateAvailableGuestUser($device);

            $this->accessProvisioningService->createCommand('create_card', $accountMemberId, $device, [
                'cards' => [[
                    'employee_id' => $guestUser->employee_id,
                    'card_no' => $cardNo,
                ]]
            ]);

            $guestUser->increment('active_cards_count');

            DailyPassCard::create([
                'device_id' => $device->id,
                'guest_user_id' => $guestUser->id,
                'card_no' => $cardNo,
                'account_member_id' => $accountMemberId,
                'charge_id' => $chargeId,
                'valid_until' => $validUntil,
                'status' => 'active',
            ]);
        }

        return $cardNo;
    }

    /**
     * Busca un guest_user activo de este dispositivo con espacio disponible.
     * Si no hay ninguno, crea uno nuevo y manda su comando create_user.
    */

    private function findOrCreateAvailableGuestUser(Device $device): GuestUser
    {
        $available = GuestUser::where('device_id', $device->id)
            ->where('status', 'active')
            ->where('active_cards_count', '<', $device->max_cards_per_user)
            ->lockForUpdate()
            ->first();

        if ($available) {
            return $available;
        }

        return $this->createNewGuestUser($device);
    }

    private function createNewGuestUser(Device $device): GuestUser
    {
        $nextNumber = GuestUser::where('device_id', $device->id)->count() + 1;
        $employeeId = sprintf('GUEST%02d%03d', $device->id, $nextNumber);

        $guestUser = GuestUser::create([
            'device_id' => $device->id,
            'employee_id' => $employeeId,
            'active_cards_count' => 0,
            'status' => 'active',
        ]);

        $this->accessProvisioningService->createCommand('create_user', null, $device, [
            'users' => [[
                'employee_id'  => $employeeId,
                'name'         => $employeeId,
                'user_type'    => 'visitor',
                'is_active'    => true,
                'is_permanent' => false,
                'valid_from'   => Carbon::now()->format('Y-m-d H:i:s'),
                'valid_to'     => Carbon::now()->addYear(10)->format('Y-m-d H:i:s')
            ]],
        ]);

        return $guestUser;
    }

    /**
     * Expira una tarjeta de pase diario: crea el comando delete_card en el
     * dispositivo, decrementa el contador del guest_user, y marca la fila
     * como expired.
    */
    public function expireCard(DailyPassCard $dailyPassCard): void
    {
        $guestUser = $dailyPassCard->guestUser;
        $device = $dailyPassCard->device;

        if (!$guestUser || !$device) {
            throw new \RuntimeException("La tarjeta {$dailyPassCard->id} referencia un guest_user o device que ya no existe en la base de datos");
        }

        $this->accessProvisioningService->createCommand('delete_card', null, $device, [
            'cards' => [[
                'employee_id' => $guestUser->employee_id,
                'card_no'     => $dailyPassCard->card_no,
            ]],
        ]);

        $guestUser->decrement('active_cards_count');

        $dailyPassCard->update(['status' => 'expired']);
    }
}


