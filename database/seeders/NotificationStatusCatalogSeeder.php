<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationStatusCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Programada', 'code' => 'scheduled'],
            ['name' => 'Pendiente', 'code' => 'pending'],
            ['name' => 'Enviada', 'code' => 'sent'],
            ['name' => 'Fallida', 'code' => 'failed'],
            ['name' => 'Cancelada', 'code' => 'cancelled'],
        ];

        foreach ($statuses as $status) {
            DB::table('notification_status_catalogs')->updateOrInsert(
                ['code' => $status['code']],
                [
                    'name' => $status['name'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
