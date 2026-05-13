<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['name' => 'Push', 'code' => 'push'],
            ['name' => 'Correo', 'code' => 'email'],
            ['name' => 'Push y correo', 'code' => 'both'],
        ];

        foreach ($channels as $channel) {
            DB::table('notification_channels')->updateOrInsert(
                ['code' => $channel['code']],
                [
                    'name' => $channel['name'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
