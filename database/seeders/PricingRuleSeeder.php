<?php

namespace Database\Seeders;

use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $types = MembershipType::all()->keyBy('code');

            // Keep the pricing matrix fully synchronized with the business rules.
            PricingRule::query()->delete();

            foreach ($this->pricingMatrix() as $item) {
                $membership = $types[$item['membership_code']] ?? null;

                if (!$membership) {
                    continue;
                }

                foreach ($item['rules'] as $rule) {
                    $fromMembershipId = null;

                    if (!empty($rule['from_membership_code'])) {
                        $fromMembershipId = $types[$rule['from_membership_code']]->id ?? null;
                    }

                    PricingRule::create([
                        'membership_type_id' => $membership->id,
                        'from_membership_type_id' => $fromMembershipId,
                        'min_age' => $rule['min_age'],
                        'max_age' => $rule['max_age'],
                        'requires_origin_family' => $rule['requires_origin_family'],
                        'requires_multiple_clubs' => $rule['requires_multiple_clubs'],
                        'monthly_fee' => $rule['monthly_fee'],
                        'inscription_fee' => $rule['inscription_fee'],
                        'priority' => $rule['priority'],
                    ]);
                }
            }
        });
    }

    protected function pricingMatrix(): array
    {
        return array_merge(
            $this->pe1Rules(),
            $this->pe2CategoryRules(),
            $this->pe2MonthlyPassRules(),
            $this->pe2PackageRules()
        );
    }

    protected function pe1Rules(): array
    {
        return [
            [
                'membership_code' => 'PE1_IND',
                'rules' => [
                    $this->rule(null, 1500, 45000, 3),
                    $this->rule('PE1_SOL', 1500, 0, 2),
                    $this->rule('PE1_FAM', 1500, 0, 1),
                ],
            ],
            [
                'membership_code' => 'PE1_FAM',
                'rules' => [
                    $this->rule(null, 3000, 85000, 4),
                    $this->rule('PE1_IND', 3000, 4800, 3),
                    $this->rule(null, 3700, 85000, 2, null, null, false, true),
                    $this->rule('PE1_IND', 3700, 4800, 1, null, null, false, true),
                ],
            ],
            [
                'membership_code' => 'PE1_SOL',
                'rules' => [
                    $this->rule('PE1_FAM', 750, 0, 2, 24, 26, true),
                    $this->rule('PE1_FAM', 925, 0, 1, 24, 26, true, true),
                ],
            ],
        ];
    }

    protected function pe2CategoryRules(): array
    {
        $categories = [
            [
                'suffix' => 'ASC',
                'family_code' => 'PE2_FAM_ASC',
                'individual_code' => 'PE2_IND_ASC',
                'solidaria_code' => 'PE2_SOL_ASC',
                'family_inscription_fee' => 80000,
                'individual_inscription_fee' => 40000,
                'family_upgrade_fee' => 4800,
            ],
            [
                'suffix' => 'EXT',
                'family_code' => 'PE2_FAM_EXT',
                'individual_code' => 'PE2_IND_EXT',
                'solidaria_code' => 'PE2_SOL_EXT',
                'family_inscription_fee' => 160000,
                'individual_inscription_fee' => 80000,
                'family_upgrade_fee' => 4800,
            ],
            [
                'suffix' => 'BEN',
                'family_code' => 'PE2_FAM_BEN',
                'individual_code' => 'PE2_IND_BEN',
                'solidaria_code' => 'PE2_SOL_BEN',
                'family_inscription_fee' => 50000,
                'individual_inscription_fee' => 25000,
                'family_upgrade_fee' => 4800,
            ],
            [
                'suffix' => 'DOC',
                'family_code' => 'PE2_FAM_DOC',
                'individual_code' => 'PE2_IND_DOC',
                'solidaria_code' => 'PE2_SOL_DOC',
                'family_inscription_fee' => 0,
                'individual_inscription_fee' => 0,
                'family_upgrade_fee' => 0,
            ],
        ];

        $rules = [];

        foreach ($categories as $category) {
            $rules[] = [
                'membership_code' => $category['individual_code'],
                'rules' => [
                    $this->rule(null, 1800, $category['individual_inscription_fee'], 3),
                    $this->rule($category['solidaria_code'], 1800, 0, 2),
                    $this->rule($category['family_code'], 1800, 0, 1),
                ],
            ];

            $rules[] = [
                'membership_code' => $category['family_code'],
                'rules' => [
                    $this->rule(null, 3600, $category['family_inscription_fee'], 4),
                    $this->rule($category['individual_code'], 3600, $category['family_upgrade_fee'], 3),
                    $this->rule(null, 3700, $category['family_inscription_fee'], 2, null, null, false, true),
                    $this->rule($category['individual_code'], 3700, $category['family_upgrade_fee'], 1, null, null, false, true),
                ],
            ];

            $rules[] = [
                'membership_code' => $category['solidaria_code'],
                'rules' => [
                    $this->rule($category['family_code'], 900, 0, 2, 24, 26, true),
                    $this->rule($category['family_code'], 925, 0, 1, 24, 26, true, true),
                ],
            ];
        }

        return $rules;
    }

    protected function pe2MonthlyPassRules(): array
    {
        return [
            [
                'membership_code' => 'PE2_PM_IND',
                'rules' => [
                    $this->rule(null, 3600, 0, 1),
                ],
            ],
            [
                'membership_code' => 'PE2_PM_FAM',
                'rules' => [
                    $this->rule(null, 7200, 0, 1),
                ],
            ],
        ];
    }

    protected function pe2PackageRules(): array
    {
        return [
            [
                'membership_code' => 'PE2_IND_PE1',
                'rules' => [
                    $this->rule(null, 1850, 25000, 1),
                ],
            ],
            [
                'membership_code' => 'PE2_FAM_PE1',
                'rules' => [
                    $this->rule(null, 3700, 50000, 1),
                ],
            ],
        ];
    }

    protected function rule(
        ?string $fromMembershipCode,
        float|int $monthlyFee,
        float|int $inscriptionFee,
        int $priority,
        ?int $minAge = null,
        ?int $maxAge = null,
        bool $requiresOriginFamily = false,
        bool $requiresMultipleClubs = false
    ): array {
        return [
            'from_membership_code' => $fromMembershipCode,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'requires_origin_family' => $requiresOriginFamily,
            'requires_multiple_clubs' => $requiresMultipleClubs,
            'monthly_fee' => $monthlyFee,
            'inscription_fee' => $inscriptionFee,
            'priority' => $priority,
        ];
    }
}
