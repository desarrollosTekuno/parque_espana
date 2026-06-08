<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Catalogs\DocumentType;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\MembershipTypeRequiredDocument;
use Illuminate\Support\Facades\DB;

class MembershipTypeRequiredDocumentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $types = MembershipType::all()->keyBy('code');
            $documents = DocumentType::all()->keyBy('code');

            $data = [

                // =========================
                // PE1 - INDIVIDUAL
                // =========================
                [
                    'membership_code' => 'PE1_IND',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'formato_datos_clinicos', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE1 - FAMILIAR
                // =========================
                [
                    'membership_code' => 'PE1_FAM',
                    'documents' => [
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'formato_datos_clinicos', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE1 - FAM BEN
                // =========================
                [
                    'membership_code' => 'PE1_FAM_BEN',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'carta_no_adeudo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],
                // PE1 - IND BEN
                [
                    'membership_code' => 'PE1_IND_BEN',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'carta_no_adeudo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE2 - IND ASC
                // =========================
                [
                    'membership_code' => 'PE2_IND_ASC',
                    'documents' => [
                        ['code' => 'acta_nacimiento_espanola', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'constancia_trabajo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE2 - FAM ASC
                // =========================
                [
                    'membership_code' => 'PE2_FAM_ASC',
                    'documents' => [
                        ['code' => 'acta_nacimiento_espanola', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'constancia_estudios', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'constancia_trabajo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE2 - IND EXT
                // =========================
                [
                    'membership_code' => 'PE2_IND_EXT',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'constancia_trabajo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE2 - FAM EXT
                // =========================
                [
                    'membership_code' => 'PE2_FAM_EXT',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'constancia_estudios', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'constancia_trabajo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE2 - FAM BEN
                // =========================
                [
                    'membership_code' => 'PE2_FAM_BEN',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'carta_no_adeudo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],
                // PE2 - IND BEN
                [
                    'membership_code' => 'PE2_IND_BEN',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'carta_no_adeudo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],

                // =========================
                // PE2 - FAM DOC
                // =========================
                [
                    'membership_code' => 'PE2_FAM_DOC',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'carta_doctor_activo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],
                // PE2 - IND DOC
                [
                    'membership_code' => 'PE2_IND_DOC',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'carta_doctor_activo', 'required' => true, 'multiple' => false, 'files' => 1],
                    ]
                ],
                [
                    'membership_code' => 'PE2_PM_IND',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'constancia_trabajo', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'ine', 'required' => true, 'multiple' => true, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                    ],
                ],
                [
                    'membership_code' => 'PE2_PM_FAM',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'constancia_trabajo', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'constancia_estudios', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'carta_recomendacion', 'required' => true, 'multiple' => true, 'files' => 2],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                    ],
                ],
                [
                    'membership_code' => 'PE2_IND_PE1',
                    'documents' => [
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'recibo_pe1_sin_adeudo', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                    ],
                ],
                [
                    'membership_code' => 'PE2_FAM_PE1',
                    'documents' => [
                        ['code' => 'acta_matrimonio', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'acta_nacimiento', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'fotografia_infantil', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'recibo_pe1_sin_adeudo', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'ine', 'required' => true, 'multiple' => false, 'files' => 1],
                        ['code' => 'comprobante_domicilio', 'required' => true, 'multiple' => false, 'files' => 1],
                    ],
                ],

            ];

            foreach ($data as $item) {

                $membership = $types[$item['membership_code']] ?? null;

                if (!$membership)
                    continue;

                foreach ($item['documents'] as $doc) {

                    $documentType = $documents[$doc['code']] ?? null;

                    if (!$documentType)
                        continue;

                    MembershipTypeRequiredDocument::updateOrCreate(
                        [
                            'membership_type_id' => $membership->id,
                            'document_type_id' => $documentType->id,
                        ],
                        [
                            'allow_multiple' => $doc['multiple'],
                            'is_required' => $doc['required'],
                            'number_files' => $doc['files'],
                        ]
                    );
                }
            }
        });
    }
}