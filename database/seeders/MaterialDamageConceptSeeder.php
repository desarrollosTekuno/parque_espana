<?php

namespace Database\Seeders;

use App\Models\Billing\ChargeConcept;
use Illuminate\Database\Seeder;

class MaterialDamageConceptSeeder extends Seeder
{
    public function run(): void
    {
        $concept = ChargeConcept::withTrashed()->updateOrCreate(
            ['code' => 'CD'],
            [
                'internal_key' => 'CD',
                'name' => 'Cargo por daños materiales',
                'description' => null,
                'default_amount' => null,
                'allows_manual_amount' => true,
                'is_recurring' => false,
                'allows_partial_payments' => false,
                'is_mobile_payable' => false,
                'splits_between_parks' => false,
                'applies_iva' => false,
                'is_active' => true,
                'requires_account' => true,
            ]
        );

        if ($concept->trashed()) {
            $concept->restore();
        }
    }
}
