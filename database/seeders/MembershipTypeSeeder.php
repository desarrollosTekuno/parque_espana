<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MembershipTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
                            ]
                        ]
                    ],
                    [
                        'name' => 'Familiar',
                        'description' => 'Membresía familiar',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3000,
                                'inscription_fee' => 85000,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => true,
                                'monthly_fee' => 700,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ]
                    ],
                    [
                        'name' => 'Solidaria',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada',
                        'is_spanish_descent' => false,
                        'requires_origin_family' => true,
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
                                'monthly_fee' => 125,
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
                        'requires_origin_family' => false,
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
                        ]
                    ],
                    [
                        'name' => 'Familiar(Ascendencia Española)',
                        'description' => 'Membresía familiar para personas con ascendencia española',
                        'is_spanish_descent' => true,
                        'requires_origin_family' => false,
                        'pricing_rules' => [
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3500,
                                'inscription_fee' => 80000,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => null,
                                'max_age' => 23,
                                'requires_origin_family' => false,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 3500,
                                'inscription_fee' => 4800,
                                'priority' => 1,
                                'from_membership' => 'Individual(Ascendencia Española)'
                            ]
                        ]
                    ],
                    [
                        'name' => 'Solidaria(Ascendencia Española)',
                        'description' => 'Membresía solidaria para personas de 24 a 26 años con familia de origen asociada y ascendencia española',
                        'is_spanish_descent' => true,
                        'requires_origin_family' => true,
                        'pricing_rules' => [
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => false,
                                'monthly_fee' => 900,
                                'inscription_fee' => 0,
                                'priority' => 2,
                                'from_membership' => null
                            ],
                            [
                                'min_age' => 24,
                                'max_age' => 26,
                                
                                'requires_origin_family' => true,
                                'requires_multiple_clubs' => true,
                                'monthly_fee' => 150,
                                'inscription_fee' => 0,
                                'priority' => 1,
                                'from_membership' => null
                            ]
                        ]
                    ]
                   
                ]

            ]
        ];
    }
}
