<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Models\Memberships\SeparationReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FamilySeparationTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $club = $this->createClub();
            $relationships = $this->createRelationships();
            $types = $this->createMembershipTypes($club);

            $this->createSeparationCatalog($relationships['spouse']);
            $this->createPricingRule($types['family'], $types['individual']);
            $this->createChargeConcept();
            $this->deleteTestAccounts();

            $this->createAccountScenario(
                membershipNumber: 'TEST-FAM-SEP-CON-001',
                accountType: 'family',
                membershipType: $types['family'],
                club: $club,
                relationships: [
                    ['key' => 'holder', 'email' => 'titular.conyuge@example.test', 'primary' => true],
                    ['key' => 'spouse', 'email' => 'conyuge.separar@example.test', 'primary' => false],
                ],
                monthlyFee: 3000
            );

            $this->createAccountScenario(
                membershipNumber: 'TEST-FAM-SEP-HIJ-001',
                accountType: 'family',
                membershipType: $types['family'],
                club: $club,
                relationships: [
                    ['key' => 'holder', 'email' => 'titular.hijo@example.test', 'primary' => true],
                    ['key' => 'child', 'email' => 'hijo.separar@example.test', 'primary' => false],
                ],
                monthlyFee: 3000
            );

            $this->createAccountScenario(
                membershipNumber: 'TEST-IND-SEP-001',
                accountType: 'individual',
                membershipType: $types['individual'],
                club: $club,
                relationships: [
                    ['key' => 'holder', 'email' => 'titular.individual@example.test', 'primary' => true],
                ],
                monthlyFee: 1500
            );
        });
    }

    private function createClub(): Club
    {
        return Club::updateOrCreate(
            ['code' => 'PE1'],
            [
                'name' => 'Parque Espana 1',
                'address' => 'Calle 1',
                'is_active' => true,
            ]
        );
    }

    private function createRelationships(): array
    {
        return [
            'holder' => Relationship::firstOrCreate(['name' => 'Titular']),
            'spouse' => Relationship::where('name', 'like', '%nyuge')->first()
                ?? Relationship::create(['name' => 'Conyuge']),
            'child' => Relationship::firstOrCreate(['name' => 'Hijo']),
        ];
    }

    private function createMembershipTypes(Club $club): array
    {
        $familyType = MembershipType::updateOrCreate(
            ['code' => 'PE1_FAM'],
            [
                'name' => 'Familiar',
                'description' => 'Membresia familiar de prueba',
                'requires_origin_family' => false,
                'show_in_listing' => true,
                'is_spanish_descent' => false,
                'allows_multiple_members' => true,
                'club_id' => $club->id,
            ]
        );

        $individualType = MembershipType::updateOrCreate(
            ['code' => 'PE1_IND'],
            [
                'name' => 'Individual',
                'description' => 'Membresia individual de prueba',
                'requires_origin_family' => false,
                'show_in_listing' => true,
                'is_spanish_descent' => false,
                'allows_multiple_members' => false,
                'club_id' => $club->id,
            ]
        );

        return [
            'family' => $familyType,
            'individual' => $individualType,
        ];
    }

    private function createSeparationCatalog(Relationship $spouseRelationship): void
    {
        $divorceDocument = DocumentType::updateOrCreate(
            ['code' => 'acta_divorcio'],
            [
                'name' => 'Acta de Divorcio',
                'description' => 'Documento que respalda la separacion del conyuge por divorcio.',
                'allowed_extensions' => 'pdf,jpg,png',
            ]
        );

        SeparationReason::updateOrCreate(
            ['code' => 'divorce'],
            [
                'name' => 'Divorcio',
                'relationship_id' => $spouseRelationship->id,
                'document_type_id' => $divorceDocument->id,
                'requires_document' => true,
                'is_active' => true,
            ]
        );
    }

    private function createPricingRule(MembershipType $familyType, MembershipType $individualType): void
    {
        PricingRule::updateOrCreate(
            [
                'membership_type_id' => $individualType->id,
                'from_membership_type_id' => $familyType->id,
                'min_age' => null,
                'max_age' => null,
                'requires_origin_family' => false,
                'requires_multiple_clubs' => false,
            ],
            [
                'monthly_fee' => 1500,
                'inscription_fee' => 0,
                'priority' => 1,
                'is_active' => true,
            ]
        );
    }

    private function createChargeConcept(): void
    {
        ChargeConcept::updateOrCreate(
            ['code' => 'MONTHLY_FEE'],
            [
                'name' => 'Mensualidad',
                'description' => 'Cargo mensual recurrente de la membresia.',
                'default_amount' => null,
                'is_recurring' => true,
                'allows_partial_payments' => false,
                'is_active' => true,
            ]
        );
    }

    private function deleteTestAccounts(): void
    {
        $testAccounts = MembershipAccount::query()
            ->where('membership_number', 'like', 'TEST-%')
            ->orWhereHas('originAccount', function ($query) {
                $query->where('membership_number', 'like', 'TEST-%');
            })
            ->orderByDesc('id')
            ->get();

        foreach ($testAccounts as $account) {
            $membershipIds = Membership::where('membership_account_id', $account->id)->pluck('id');

            if ($membershipIds->isNotEmpty()) {
                DB::table('memberships.membership_history')
                    ->whereIn('membership_id', $membershipIds)
                    ->delete();
            }

            Charge::where('membership_account_id', $account->id)->delete();
            Membership::where('membership_account_id', $account->id)->delete();
            MembershipAccountMember::where('membership_account_id', $account->id)->delete();
            $account->delete();
        }
    }

    private function createAccountScenario(
        string $membershipNumber,
        string $accountType,
        MembershipType $membershipType,
        Club $club,
        array $relationships,
        int $monthlyFee
    ): void {
        $account = MembershipAccount::factory()
            ->state([
                'membership_number' => $membershipNumber,
                'internal_account_number' => $membershipNumber,
                'club_id' => $club->id,
                'account_type' => $accountType,
                'status' => 'active',
            ])
            ->create();

        foreach ($relationships as $memberData) {
            $memberAttributes = Member::factory()->make([
                'email' => $memberData['email'],
                'occupation' => 'Pruebas',
            ])->getAttributes();

            $member = Member::updateOrCreate(
                ['email' => $memberData['email']],
                $memberAttributes
            );

            MembershipAccountMember::factory()
                ->state([
                    'membership_account_id' => $account->id,
                    'member_id' => $member->id,
                    'relationship_id' => $this->relationshipId($memberData['key']),
                    'is_primary_holder' => $memberData['primary'],
                ])
                ->create();
        }

        Membership::factory()
            ->active()
            ->state([
                'membership_account_id' => $account->id,
                'club_id' => $club->id,
                'membership_type_id' => $membershipType->id,
                'monthly_fee' => $monthlyFee,
                'monthly_fee_total' => $monthlyFee,
                'monthly_fee_share' => $monthlyFee,
            ])
            ->create();
    }

    private function relationshipId(string $key): ?int
    {
        $name = match ($key) {
            'holder' => 'Titular',
            'child' => 'Hijo',
            default => 'Conyuge',
        };

        return Relationship::where('name', $name)->value('id')
            ?? Relationship::where('name', 'like', '%nyuge')->value('id');
    }
}
