<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Catalogs\DocumentType;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\MembershipTypeRequiredDocument;
use App\Models\Memberships\PricingRule;
use Illuminate\Database\Seeder;

class MembershipTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //carta de no adeudo no va en doctor beneficencia
        //
        $membershipTypes = [
            [
                'code_club' => 'PE1',
                'memberships' => [
                    [
                        'name' => 'Individual',
                        'description' => 'Membresía individual',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => null,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 1500,
                                'inscription_fee' => 45000,
                                'priority' => 1,
                                'from_membership' => null
                            ],

                        ],
                        'documents' => [
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // formato de datos clinicos
                            [
                                'code' => 'formato_datos_clinicos',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ]
                        ]
                    ],
                    [
                        'name' => 'Familiar',
                        'description' => 'Membresía familiar',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => true,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3000,
                                'inscription_fee' => 85000,
                                'priority' => 3,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => true,
                                'monthly_fee' => 3700,
                                'inscription_fee' => 0,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            // viene de Individual
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3000,
                                'inscription_fee' => 4800,
                                'priority' => 1,
                                'from_membership' => 'Individual'
                            ]
                        ],
                        'documents' => [
                            // acta de matrimonio
                            [
                                'code' => 'acta_matrimonio',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // formato de datos clinicos
                            [
                                'code' => 'formato_datos_clinicos',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                        ]
                    ],
                    [
                        'name' => 'Solidaria',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => true,
                        'show_in_listing' => false,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 750,
                                'inscription_fee' => 0,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => true,
                                'monthly_fee' => 925,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ]
                    ]
                ]

            ],
            [
                'code_club' => 'PE2',
                'memberships' => [
                    [
                        'name' => 'Individual(Ascendencia Española)',
                        'description' => 'Membresía individual para personas con ascendencia española',
                        'is_spanish_descent' => true,
                        'show_in_listing' => true,
                        'requires_origin_family' => false,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => null,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 1800,
                                'inscription_fee' => 40000,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ],
                        'documents' => [
                            [
                                'code' => 'acta_nacimiento_espanola',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                        ]
                    ],
                    [
                        'name' => 'Familiar(Ascendencia Española)',
                        'description' => 'Membresía familiar para personas con ascendencia española',
                        'is_spanish_descent' => true,
                        'requires_origin_family' => false,
                        'allows_multiple_members' => true,
                        'show_in_listing' => true,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 80000,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 4800,
                                'priority' => 1,
                                'from_membership' => 'Individual(Ascendencia Española)'
                            ]
                        ],
                        'documents' => [
                            [
                                'code' => 'acta_nacimiento_espanola',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // acta de matrimonio
                            [
                                'code' => 'acta_matrimonio',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // acta de nacimiento
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de estudios
                            [
                                'code' => 'constancia_estudios',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                        ]
                    ],
                    [
                        'name' => 'Solidaria(Ascendencia Española)',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada y ascendencia española',
                        'is_spanish_descent' => true,
                        'requires_origin_family' => true,
                        'show_in_listing' => false,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 900,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ],
                        ]
                    ],
                    [
                        'name' => 'Individual(Externos)',
                        'description' => 'Membresía individual para personas externas',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => null,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 1800,
                                'inscription_fee' => 80000,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ],
                        'documents' => [
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                        ]

                    ],
                    [
                        'name' => 'Familiar(Externos)',
                        'description' => 'Membresía familiar para personas externas',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => true,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 160000,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 4800,
                                'priority' => 1,
                                'from_membership' => 'Individual(Externos)'
                            ]
                        ],
                        'documents' => [
                            // acta de matrimonio
                            [
                                'code' => 'acta_matrimonio',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // acta de nacimiento
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de estudios
                            [
                                'code' => 'constancia_estudios',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                        ]
                    ],
                    [
                        'name' => 'Solidaria(Externos)',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada y externas',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => true,
                        'allows_multiple_members' => true,
                        'show_in_listing' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 900,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ],
                        ]
                    ],
                    [
                        'name' => 'Individual(Beneficencia Española)',
                        'description' => 'Membresía individual para personas de beneficencia española',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => null,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 1800,
                                'inscription_fee' => 25000,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ],
                        'documents' => [
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // carta no adeudo
                            [
                                'code' => 'carta_no_adeudo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                        ]

                    ],
                    [
                        'name' => 'Familiar(Beneficencia Española)',
                        'description' => 'Membresía familiar para personas de beneficencia española',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => true,

                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 50000,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 4800,
                                'priority' => 1,
                                'from_membership' => 'Individual(Beneficencia Española)'
                            ]
                        ],
                        'documents' => [
                            // acta de matrimonio
                            [
                                'code' => 'acta_matrimonio',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // acta de nacimiento
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de estudios
                            [
                                'code' => 'constancia_estudios',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // carta no adeudo
                            [
                                'code' => 'carta_no_adeudo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ]
                        ]
                    ],
                    [
                        'name' => 'Solidaria(Beneficencia Española)',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada y de beneficencia española',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => true,
                        'show_in_listing' => false,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 900,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ],
                        ]
                    ],
                    [
                        'name' => 'Individual(Doctores Beneficencia Española)',
                        'description' => 'Membresía individual para doctores de beneficencia española',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => null,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 1800,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ],
                        'documents' => [
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // carta no adeudo
                            [
                                'code' => 'carta_no_adeudo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // carta doctor activo
                            [
                                'code' => 'carta_doctor_activo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                        ]

                    ],
                    [
                        'name' => 'Familiar(Doctores Beneficencia Española)',
                        'description' => 'Membresía familiar para doctores de beneficencia española',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'show_in_listing' => true,
                        'allows_multiple_members' => true,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 0,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3600,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => 'Individual(Doctores Beneficencia Española)'
                            ]
                        ],
                        'documents' => [
                            // acta de matrimonio
                            [
                                'code' => 'acta_matrimonio',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // acta de nacimiento
                            [
                                'code' => 'acta_nacimiento',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // ine
                            [
                                'code' => 'ine',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // comprobante de domicilio
                            [
                                'code' => 'comprobante_domicilio',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // fotografia tamaño infantil
                            [
                                'code' => 'fotografia_infantil',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // carta de recomendacion
                            [
                                'code' => 'carta_recomendacion',
                                'allow_multiple' => true,
                                'is_required' => true,
                                'number_files' => 2,
                            ],
                            // constancia de estudios
                            [
                                'code' => 'constancia_estudios',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // constancia de trabajo
                            [
                                'code' => 'constancia_trabajo',
                                'allow_multiple' => false,
                                'is_required' => false,
                                'number_files' => 1,
                            ],
                            // carta no adeudo
                            [
                                'code' => 'carta_no_adeudo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                            // carta doctor activo
                            [
                                'code' => 'carta_doctor_activo',
                                'allow_multiple' => false,
                                'is_required' => true,
                                'number_files' => 1,
                            ],
                        ]
                    ],
                    [
                        'name' => 'Solidaria(Doctores Beneficencia Española)',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada y doctores de beneficencia española',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => true,
                        'show_in_listing' => false,
                        'allows_multiple_members'=> false,
                        'pricing_rules' => [
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 900,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ],
                        ]
                    ],


                ]

            ]
        ];

        foreach ($membershipTypes as $clubData) {
            $club = Club::where('code', $clubData['code_club'])->first();

            foreach ($clubData['memberships'] as $membershipData) {
                $membership = MembershipType::create([
                    'name' => $membershipData['name'],
                    'description' => $membershipData['description'],
                    'is_spanish_descent' => $membershipData['is_spanish_descent'],
                    'requires_origin_family' => $membershipData['requires_origin_family'],
                    'show_in_listing' => $membershipData['show_in_listing'],
                    'allows_multiple_members' => $membershipData['allows_multiple_members'],
                    'club_id' => $club->id
                ]);


                foreach ($membershipData['pricing_rules'] as $ruleData) {
                    PricingRule::create([
                        'min_age' => $ruleData['min_age'],
                        'max_age' => $ruleData['max_age'],
                        'requires_origin_family' => $ruleData['requires_origin_family'],
                        'requires_multiple_clubs' => $ruleData['requires_multiple_clubs'],
                        'monthly_fee' => $ruleData['monthly_fee'],
                        'inscription_fee' => $ruleData['inscription_fee'],
                        'priority' => $ruleData['priority'],
                        'from_membership_type_id' => $ruleData['from_membership'] ? MembershipType::where('name', $ruleData['from_membership'])->first()->id : null,
                        'membership_type_id' => $membership->id
                    ]);
                }
                // if isset documents
                if (isset($membershipData['documents'])) {

                    foreach ($membershipData['documents'] as $documentData) {
                        $documentType = DocumentType::where('code', $documentData['code'])->first();

                        MembershipTypeRequiredDocument::create([
                            'membership_type_id' => $membership->id,
                            'document_type_id' => $documentType->id,
                            'allow_multiple' => $documentData['allow_multiple'],
                            'is_required' => $documentData['is_required'],
                            'number_files' => $documentData['number_files'],
                        ]);
                    }
                }
            }
        }
    }
}
