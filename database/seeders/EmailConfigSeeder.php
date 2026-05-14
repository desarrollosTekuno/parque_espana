<?php

namespace Database\Seeders;

use App\Models\Notifications\EmailConfig;
use Illuminate\Database\Seeder;

class EmailConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'profile_name' => 'SMTP Club 1',
                'host' => 'sandbox.smtp.mailtrap.io',
                'port' => 2525,
                'username' => '2617e4562cb0b5',
                'password' => '61f99df8143820',
                'encryption' => 'tls',
                'from_address' => 'mailtrap@example.com',
                'from_name' => config('app.name'),
                'is_active' => true,
            ],
            [
                'entity_id' => 2,
                'profile_name' => 'SMTP Club 2',
                'host' => 'sandbox.smtp.mailtrap.io',
                'port' => 2525,
                'username' => '2617e4562cb0b5',
                'password' => '61f99df8143820',
                'encryption' => 'tls',
                'from_address' => 'mailtrap@example.com',
                'from_name' => config('app.name'),
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            EmailConfig::updateOrCreate(
                ['entity_id' => $item['entity_id']],
                $item
            );
        }
    }
}
