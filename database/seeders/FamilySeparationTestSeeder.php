<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Billing\ChargeConcept;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
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
            $club = Club::updateOrCreate(
                ['code' => 'PE1'],
                [
                    'name' => 'Parque Espana 1',
                    'address' => 'Calle 1',
                    'is_active' => true,
                ]
            );

            $holderRelationship = Relationship::firstOrCreate(['name' => 'Titular']);
            $spouseRelationship = Relationship::where('name', 'like', '%nyuge')->first()
                ?? Relationship::create(['name' => 'Conyuge']);

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

            $account = MembershipAccount::where('membership_number', 'TEST-FAM-SEP-001')->first();
            $accountGroup = $account?->accountGroup ?? MembershipAccountGroup::create(['status' => 'active']);

            $account = MembershipAccount::updateOrCreate(
                ['membership_number' => 'TEST-FAM-SEP-001'],
                [
                    'account_group_id' => $accountGroup->id,
                    'club_id' => $club->id,
                    'account_type' => 'family',
                    'status' => 'active',
                    'internal_account_number' => 'TEST-FAM-SEP-001',
                ]
            );

            $holder = Member::updateOrCreate(
                ['email' => 'titular.separacion@example.test'],
                [
                    'first_name' => 'Titular',
                    'last_name' => 'Prueba',
                    'second_last_name' => 'Separacion',
                    'birthdate' => '1985-01-15',
                    'phone' => '5511111111',
                    'occupation' => 'Pruebas',
                ]
            );

            $spouse = Member::updateOrCreate(
                ['email' => 'conyuge.separacion@example.test'],
                [
                    'first_name' => 'Conyuge',
                    'last_name' => 'Prueba',
                    'second_last_name' => 'Separacion',
                    'birthdate' => '1987-05-20',
                    'phone' => '5522222222',
                    'occupation' => 'Pruebas',
                ]
            );

            MembershipAccountMember::updateOrCreate(
                [
                    'membership_account_id' => $account->id,
                    'member_id' => $holder->id,
                ],
                [
                    'relationship_id' => $holderRelationship->id,
                    'is_primary_holder' => true,
                ]
            );

            MembershipAccountMember::updateOrCreate(
                [
                    'membership_account_id' => $account->id,
                    'member_id' => $spouse->id,
                ],
                [
                    'relationship_id' => $spouseRelationship->id,
                    'is_primary_holder' => false,
                ]
            );

            Membership::updateOrCreate(
                [
                    'membership_account_id' => $account->id,
                    'club_id' => $club->id,
                ],
                [
                    'membership_type_id' => $familyType->id,
                    'origin_membership_type_id' => null,
                    'is_primary' => true,
                    'is_billable' => true,
                    'monthly_fee' => 3000,
                    'monthly_fee_total' => 3000,
                    'monthly_fee_share' => 3000,
                    'billing_split_mode' => 'single',
                    'start_date' => now()->subMonths(6)->toDateString(),
                    'end_date' => null,
                    'status' => 'active',
                ]
            );
        });
    }
}
