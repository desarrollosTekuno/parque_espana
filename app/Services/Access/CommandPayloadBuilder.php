<?php

namespace App\Services\Access;

use Carbon\Carbon;

class CommandPayloadBuilder
{
    public function build(string $action, array $data): array
    {
        if (in_array($action, ['create_user', 'update_user'])) {
            $users = collect($data['users'])->map(function ($user) {
                return [
                    'employee_id'  => $user['employee_id'],
                    'name'         => $user['name'],
                    'user_type'    => $user['user_type'],
                    'is_active'    => $user['is_active'],
                    'is_permanent' => $user['is_permanent'],
                    'valid_from'   => Carbon::parse($user['valid_from'])->format('Y-m-d\TH:i:s'),
                    'valid_to'     => Carbon::parse($user['valid_to'])->format('Y-m-d\TH:i:s'),
                ];
            });

            return ['users' => $users->toArray()];
        }

        if ($action === 'delete_user') {
            return ['users' => collect($data['users'])->map(fn($u) => [
                'employee_id' => $u['employee_id'],
            ])->toArray()];
        }

        if (in_array($action, ['create_card', 'update_card'])) {
            return ['cards' => collect($data['cards'])->map(fn($c) => [
                'employee_id' => $c['employee_id'],
                'card_no'     => $c['card_no'],
            ])->toArray()];
        }

        return $data;
    }
}