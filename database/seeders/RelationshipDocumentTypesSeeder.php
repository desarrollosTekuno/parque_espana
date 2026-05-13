<?php

namespace Database\Seeders;

use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Models\Catalogs\RelationshipDocumentType;
use Illuminate\Database\Seeder;

class RelationshipDocumentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $relationshipDocumentTypes = [
            [
                'relationship' => 'Titular',
                'documents' => ['acta_nacimiento_espanola', 'acta_matrimonio', 'acta_nacimiento', 'fotografia_infantil', 'constancia_trabajo', 'carta_recomendacion', 'ine', 'comprobante_domicilio', 'carta_no_adeudo', 'recibo_pe1_sin_adeudo', 'carta_doctor_activo'],
            ],
            [
                'relationship' => 'Cónyuge',
                'documents' => ['acta_nacimiento', 'fotografia_infantil', 'ine'],
            ],
            [
                'relationship' => 'Hijo(a)',
                'documents' => ['acta_nacimiento', 'fotografia_infantil', 'constancia_estudios', 'ine'],
            ],
            [
                'relationship' => 'Madre',
                'documents' => ['acta_nacimiento', 'fotografia_infantil', 'ine'],
            ]
        ];

        foreach ($relationshipDocumentTypes as $relationshipDocumentType) {
            $relationship = Relationship::where('name', $relationshipDocumentType['relationship'])->first();

            if ($relationship) {
                foreach ($relationshipDocumentType['documents'] as $documentCode) {
                    $documentType = DocumentType::where('code', $documentCode)->first();

                    if ($documentType) {
                        RelationshipDocumentType::create([
                            'relationship_id' => $relationship->id,
                            'document_type_id' => $documentType->id,
                        ]);
                    }
                }
            }
        }
    }
}
