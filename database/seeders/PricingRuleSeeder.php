<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $types = MembershipType::all()->keyBy('code');

            $data = [

                /*
                |--------------------------------------------------------------------------
                | PE1
                |--------------------------------------------------------------------------
                */

                // PE1 - SOLIDARIA
                [
                    'membership_code' => 'PE1_SOL',
                    'rules' => [
                        [
                            'from_membership_code' => 'PE1_FAM',
                            'min_age' => 24,
                            'max_age' => 26,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 750,
                            'inscription_fee' => 0,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE1_FAM',
                            'min_age' => 24,
                            'max_age' => 26,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => true,
                            'monthly_fee' => 925,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE1 - INDIVIDUAL
                [
                    'membership_code' => 'PE1_IND',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1500,
                            'inscription_fee' => 45000,
                            'priority' => 3,
                        ],
                        [
                            'from_membership_code' => 'PE1_SOL',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1500,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                        [
                            'from_membership_code' => 'PE1_FAM',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1500,
                            'inscription_fee' => 0,
                            'priority' => 2,
                        ],
                    ],
                ],

                // PE1 - FAMILIAR
                [
                    'membership_code' => 'PE1_FAM',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3000,
                            'inscription_fee' => 85000,
                            'priority' => 3,
                        ],
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => true,
                            'monthly_fee' => 3700,
                            'inscription_fee' => 0,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE1_IND',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3000,
                            'inscription_fee' => 4800,
                            'priority' => 1,
                        ],
                    ],
                ],

                /*
                |--------------------------------------------------------------------------
                | PE2 - ASCENDENCIA ESPAÑOLA
                |--------------------------------------------------------------------------
                */

                // PE2 - SOLIDARIA ASC
                [
                    'membership_code' => 'PE2_SOL_ASC',
                    'rules' => [
                        [
                            'from_membership_code' => 'PE2_FAM_ASC',
                            'min_age' => 24,
                            'max_age' => 26,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 900,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - INDIVIDUAL ASC
                [
                    'membership_code' => 'PE2_IND_ASC',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 40000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_SOL_ASC',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - FAMILIAR ASC
                [
                    'membership_code' => 'PE2_FAM_ASC',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 80000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_IND_ASC',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 4800,
                            'priority' => 1,
                        ],
                    ],
                ],

                /*
                |--------------------------------------------------------------------------
                | PE2 - EXTERNOS
                |--------------------------------------------------------------------------
                */

                // PE2 - SOLIDARIA EXT
                [
                    'membership_code' => 'PE2_SOL_EXT',
                    'rules' => [
                        [
                            'from_membership_code' => 'PE2_FAM_EXT',
                            'min_age' => 24,
                            'max_age' => 26,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 900,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - INDIVIDUAL EXT
                [
                    'membership_code' => 'PE2_IND_EXT',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 80000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_SOL_EXT',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - FAMILIAR EXT
                [
                    'membership_code' => 'PE2_FAM_EXT',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 160000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_IND_EXT',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 4800,
                            'priority' => 1,
                        ],
                    ],
                ],

                /*
                |--------------------------------------------------------------------------
                | PE2 - BENEFICENCIA ESPAÑOLA
                |--------------------------------------------------------------------------
                */

                // PE2 - SOLIDARIA BEN
                [
                    'membership_code' => 'PE2_SOL_BEN',
                    'rules' => [
                        [
                            'from_membership_code' => 'PE2_FAM_BEN',
                            'min_age' => 24,
                            'max_age' => 26,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 900,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - INDIVIDUAL BEN
                [
                    'membership_code' => 'PE2_IND_BEN',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 25000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_SOL_BEN',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - FAMILIAR BEN
                [
                    'membership_code' => 'PE2_FAM_BEN',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 50000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_IND_BEN',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 4800,
                            'priority' => 1,
                        ],
                    ],
                ],

                /*
                |--------------------------------------------------------------------------
                | PE2 - DOCTORES BENEFICENCIA ESPAÑOLA
                |--------------------------------------------------------------------------
                */

                // PE2 - SOLIDARIA DOC
                [
                    'membership_code' => 'PE2_SOL_DOC',
                    'rules' => [
                        [
                            'from_membership_code' => 'PE2_FAM_DOC',
                            'min_age' => 24,
                            'max_age' => 26,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 900,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - INDIVIDUAL DOC
                [
                    'membership_code' => 'PE2_IND_DOC',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 0,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_SOL_DOC',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => true,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1800,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],

                // PE2 - FAMILIAR DOC
                [
                    'membership_code' => 'PE2_FAM_DOC',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 0,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_IND_DOC',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],
                [
                    'membership_code' => 'PE2_PM_IND',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3600,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],
                [
                    'membership_code' => 'PE2_PM_FAM',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 7200,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],
                [
                    'membership_code' => 'PE2_SOL_PE1',
                    'rules' => [
                        [
                            'from_membership_code' => 'PE2_FAM_PE1',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1850,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ],
                    ],
                ],
                [
                    'membership_code' => 'PE2_IND_PE1',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1850,
                            'inscription_fee' => 25000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_SOL_PE1',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 1850,
                            'inscription_fee' => 0,
                            'priority' => 1,
                        ]
                    ],
                ],
                [
                    'membership_code' => 'PE2_FAM_PE1',
                    'rules' => [
                        [
                            'from_membership_code' => null,
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3700,
                            'inscription_fee' => 50000,
                            'priority' => 2,
                        ],
                        [
                            'from_membership_code' => 'PE2_IND_PE1',
                            'min_age' => null,
                            'max_age' => null,
                            'requires_origin_family' => false,
                            'requires_multiple_clubs' => false,
                            'monthly_fee' => 3700,
                            'inscription_fee' => 4800,
                            'priority' => 1,
                        ]
                    ],
                ],
            ];

            foreach ($data as $item) {
                $membership = $types[$item['membership_code']] ?? null;

                if (!$membership) {
                    continue;
                }

                foreach ($item['rules'] as $rule) {
                    $fromMembershipId = null;

                    if (!empty($rule['from_membership_code'])) {
                        $fromMembershipId = $types[$rule['from_membership_code']]->id ?? null;
                    }

                    PricingRule::updateOrCreate(
                        [
                            'membership_type_id' => $membership->id,
                            'from_membership_type_id' => $fromMembershipId,
                            'min_age' => $rule['min_age'],
                            'max_age' => $rule['max_age'],
                            'requires_origin_family' => $rule['requires_origin_family'],
                            'requires_multiple_clubs' => $rule['requires_multiple_clubs'],
                        ],
                        [
                            'monthly_fee' => $rule['monthly_fee'],
                            'inscription_fee' => $rule['inscription_fee'],
                            'priority' => $rule['priority'],
                        ]
                    );
                }
            }
        });
    }
}
