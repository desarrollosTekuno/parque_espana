<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\InterclubPackageRule;

class InterclubPackageRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $clubs = Club::all()->keyBy('code');
            $types = MembershipType::all()->keyBy('code');

            $data = [
                // =========================================================
                // PAQUETE INTERMEDIO PE1
                // PE1 Individual -> PE2 Familiar
                // Inscripción: 50,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND',
                    'target_membership_code' => 'PE2_FAM_ASC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND',
                    'target_membership_code' => 'PE2_FAM_EXT',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND',
                    'target_membership_code' => 'PE2_FAM_BEN',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND',
                    'target_membership_code' => 'PE2_FAM_DOC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 0,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

                // =========================================================
                // PAQUETE INTERMEDIO PE1
                // PE1 Familiar -> PE2 Individual
                // Inscripción: 25,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM',
                    'target_membership_code' => 'PE2_IND_ASC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM',
                    'target_membership_code' => 'PE2_IND_EXT',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM',
                    'target_membership_code' => 'PE2_IND_BEN',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM',
                    'target_membership_code' => 'PE2_IND_DOC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 0,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

                // =========================================================
                // PAQUETE INTERMEDIO PE1 BENEFICENCIA
                // PE1 Individual Beneficencia -> PE2 Familiar
                // Inscripción: 50,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND_BEN',
                    'target_membership_code' => 'PE2_FAM_ASC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND_BEN',
                    'target_membership_code' => 'PE2_FAM_EXT',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND_BEN',
                    'target_membership_code' => 'PE2_FAM_BEN',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_IND_BEN',
                    'target_membership_code' => 'PE2_FAM_DOC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 0,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

                // =========================================================
                // PAQUETE INTERMEDIO PE1 BENEFICENCIA
                // PE1 Familiar Beneficencia -> PE2 Individual
                // Inscripción: 25,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM_BEN',
                    'target_membership_code' => 'PE2_IND_ASC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM_BEN',
                    'target_membership_code' => 'PE2_IND_EXT',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM_BEN',
                    'target_membership_code' => 'PE2_IND_BEN',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE1',
                    'target_club_code' => 'PE2',
                    'source_membership_code' => 'PE1_FAM_BEN',
                    'target_membership_code' => 'PE2_IND_DOC',
                    'package_code' => 'PE1_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 0,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

                // =========================================================
                // PAQUETE INTERMEDIO PE2
                // PE2 Individual -> PE1 Familiar
                // Inscripción: 50,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_ASC',
                    'target_membership_code' => 'PE1_FAM',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_EXT',
                    'target_membership_code' => 'PE1_FAM',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_BEN',
                    'target_membership_code' => 'PE1_FAM',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_DOC',
                    'target_membership_code' => 'PE1_FAM',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                // =========================================================
                // PAQUETE INTERMEDIO PE2
                // PE2 Familiar -> PE1 Individual
                // Inscripción: 25,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_ASC',
                    'target_membership_code' => 'PE1_IND',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_EXT',
                    'target_membership_code' => 'PE1_IND',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_BEN',
                    'target_membership_code' => 'PE1_IND',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_DOC',
                    'target_membership_code' => 'PE1_IND',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    'inscription_fee' => 25000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

                // =========================================================
                // PAQUETE INTERMEDIO PE2 -> PE1 BENEFICENCIA
                // PE2 Individual -> PE1 Familiar Beneficencia
                // Inscripción: 50,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_ASC',
                    'target_membership_code' => 'PE1_FAM_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 42500,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_EXT',
                    'target_membership_code' => 'PE1_FAM_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 42500,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_BEN',
                    'target_membership_code' => 'PE1_FAM_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 42500,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_IND_DOC',
                    'target_membership_code' => 'PE1_FAM_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 42500,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

                // =========================================================
                // PAQUETE INTERMEDIO PE2 -> PE1 BENEFICENCIA
                // PE2 Familiar -> PE1 Individual Beneficencia
                // Inscripción: 25,000
                // Mensualidad: 3,650
                // =========================================================
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_ASC',
                    'target_membership_code' => 'PE1_IND_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 21250,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_EXT',
                    'target_membership_code' => 'PE1_IND_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 21250,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_BEN',
                    'target_membership_code' => 'PE1_IND_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 21250,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],
                [
                    'source_club_code' => 'PE2',
                    'target_club_code' => 'PE1',
                    'source_membership_code' => 'PE2_FAM_DOC',
                    'target_membership_code' => 'PE1_IND_BEN',
                    'package_code' => 'PE2_INTERMEDIATE_PACKAGE',
                    'min_years_in_source_club' => null,
                    'requires_active_source_membership' => true,
                    // 'inscription_fee' => 21250,
                    'inscription_fee' => 50000,
                    'monthly_fee' => 3650,
                    'priority' => 1,
                ],

            ];

            foreach ($data as $item) {
                $sourceClub = $clubs[$item['source_club_code']] ?? null;
                $targetClub = $clubs[$item['target_club_code']] ?? null;
                $sourceMembership = $types[$item['source_membership_code']] ?? null;
                $targetMembership = $types[$item['target_membership_code']] ?? null;

                if (!$sourceClub || !$targetClub || !$sourceMembership || !$targetMembership) {
                    continue;
                }

                InterclubPackageRule::updateOrCreate(
                    [
                        'source_club_id' => $sourceClub->id,
                        'target_club_id' => $targetClub->id,
                        'source_membership_type_id' => $sourceMembership->id,
                        'target_membership_type_id' => $targetMembership->id,
                        'package_code' => $item['package_code'],
                    ],
                    [
                        'min_years_in_source_club' => $item['min_years_in_source_club'],
                        'requires_active_source_membership' => $item['requires_active_source_membership'],
                        'inscription_fee' => $item['inscription_fee'],
                        'monthly_fee' => $item['monthly_fee'],
                        'priority' => $item['priority'],
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}