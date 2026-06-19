<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('billing.concepts')->where('code', 'PHYSICAL_AD')->exists();

        if (!$exists) {
            DB::table('billing.concepts')->insert([
                'code'                    => 'PHYSICAL_AD',
                'name'                    => 'Anuncio Físico',
                'description'             => 'Cobro por anuncio físico en el club',
                'default_amount'          => null,
                'is_recurring'            => false,
                'allows_partial_payments' => false,
                'is_active'               => true,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('billing.concepts')->where('code', 'PHYSICAL_AD')->delete();
    }
};
