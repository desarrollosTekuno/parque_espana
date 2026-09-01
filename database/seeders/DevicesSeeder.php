<?php

namespace Database\Seeders;

use App\Models\Devices\Device;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DevicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $devices = [
            [
                'id' => 1,
                'name' => 'Dispositivo PE1',
                'ip' => '192.168.1.1',
                'status' => 'active',
                'club_id' => 1,
                'user' => 'admin',
                'password' => 'password',
                'port' => 80,
                'use_https' => false
            ],
            [
                'id' => 2,
                'name' => 'Dispositivo PE2',
                'ip' => '192.168.1.1',
                'status' => 'active',
                'club_id' => 2,
                'user' => 'admin',
                'password' => 'password',
                'port' => 80,
                'use_https' => false
            ]
        ];

        foreach ($devices as $device) {
            Device::updateOrCreate(
            [
                'id' => $device['id']
            ],
            [
                'name' => $device['name'],
                'ip' => $device['ip'],
                'status' => $device['status'],
                'club_id' => $device['club_id'],
                'user' => $device['user'],
                'password' => $device['password'],
                'port' => $device['port'],
                'use_https' => $device['use_https']
            ]);
        }
    }
}
