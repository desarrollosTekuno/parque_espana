<?php

namespace Database\Seeders;

use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Models\Memberships\SeparationReason;
use Illuminate\Database\Seeder;

class SeparationReasonSeeder extends Seeder {

    public function run(): void {
        $spouseRelationship = Relationship::whereIn('name', ['Cónyuge', 'Conyuge'])->first();
        $divorceDocument = DocumentType::where('code', 'acta_divorcio')->first();

        $reasons = [
            [
                'code' => 'divorce',
                'name' => 'Divorcio',
                'relationship_id' => $spouseRelationship?->id,
                'document_type_id' => $divorceDocument?->id,
                'requires_document' => true,
                'is_active' => true,
            ],
        ];

        foreach ($reasons as $reason) {
            SeparationReason::updateOrCreate(
                ['code' => $reason['code']],
                $reason
            );
        }
    }
}
