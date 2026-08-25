<?php

namespace Database\Factories\Memberships;

use App\Models\Administrator\Club;
use App\Models\Catalogs\Relationship;
use App\Models\Members\Locker;
use App\Models\Members\LockerAssignment;
use App\Models\Members\Member;
use App\Models\Memberships\AccountFiscalData;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<MembershipAccount>
 */
class MembershipAccountFactory extends Factory
{
    protected $model = MembershipAccount::class;

    public function definition(): array
    {
        $club = Club::query()->orderBy('id')->first();

        if (!$club) {
            throw new RuntimeException('No hay parques disponibles para generar cuentas de prueba.');
        }

        $group = MembershipAccountGroup::query()->create([
            'status' => 'active',
        ]);
        $number = strtoupper($club->code) . '-' . $this->faker->unique()->numerify('9#####');

        return [
            'account_group_id' => $group->id,
            'club_id' => $club->id,
            'membership_number' => $number,
            'internal_account_number' => 'TKT-' . $number,
            'account_type' => 'individual',
            'status' => 'active',
        ];
    }

    public function forClub(Club $club): static
    {
        return $this->state(function () use ($club) {
            $number = strtoupper($club->code) . '-' . $this->faker->unique()->numerify('9#####');

            return [
                'club_id' => $club->id,
                'membership_number' => $number,
                'internal_account_number' => 'TKT-' . $number,
            ];
        });
    }

    public function individual(): static
    {
        return $this->state([
            'account_type' => 'individual',
        ]);
    }

    public function family(): static
    {
        return $this->state([
            'account_type' => 'family',
        ]);
    }

    public function withMembers(int $dependentCount = 0): static
    {
        return $this->afterCreating(function (MembershipAccount $account) use ($dependentCount) {
            $holder = Member::factory()->create([
                'first_name' => 'Titular',
                'last_name' => 'Prueba',
                'second_last_name' => 'Cuenta ' . $account->id,
                'birthdate' => '1985-05-15',
                'phone' => '555100' . str_pad((string) $account->id, 4, '0', STR_PAD_LEFT),
                'email' => "ticket.titular.{$account->id}@example.test",
            ]);

            MembershipAccountMember::query()->create([
                'membership_account_id' => $account->id,
                'member_id' => $holder->id,
                'relationship_id' => $this->relationshipId('Titular'),
                'is_primary_holder' => true,
            ]);

            for ($index = 1; $index <= $dependentCount; $index++) {
                $isChild = $index > 1;
                $dependent = Member::factory()->create([
                    'first_name' => 'Dependiente ' . $index,
                    'last_name' => 'Prueba',
                    'second_last_name' => 'Cuenta ' . $account->id,
                    'birthdate' => $isChild ? '2015-08-10' : '1987-02-20',
                    'phone' => '555200' . str_pad((string) ($account->id + $index), 4, '0', STR_PAD_LEFT),
                    'email' => "ticket.dependiente.{$account->id}.{$index}@example.test",
                ]);

                MembershipAccountMember::query()->create([
                    'membership_account_id' => $account->id,
                    'member_id' => $dependent->id,
                    'relationship_id' => $this->relationshipId($isChild ? 'Hijo(a)' : 'Cónyuge'),
                    'is_primary_holder' => false,
                ]);
            }
        });
    }

    public function withActiveMembership(): static
    {
        return $this->afterCreating(function (MembershipAccount $account) {
            $typeCode = match ($account->club?->code) {
                'PE1' => $account->account_type === 'family' ? 'PE1_FAM' : 'PE1_IND',
                'PE2' => $account->account_type === 'family' ? 'PE2_FAM_ASC' : 'PE2_IND_ASC',
                default => null,
            };
            $membershipType = MembershipType::query()
                ->where('club_id', $account->club_id)
                ->where('code', $typeCode)
                ->first();

            if (!$membershipType) {
                throw new RuntimeException("No se encontró un tipo de membresía para {$account->club?->code}.");
            }

            $pricingRule = $membershipType->pricingRules()
                ->where('is_active', true)
                ->orderBy('priority')
                ->first();
            $monthlyFee = $pricingRule?->resolveMonthlyFee()
                ?? ($account->account_type === 'family' ? 3000 : 1500);

            Membership::query()->create([
                'membership_account_id' => $account->id,
                'club_id' => $account->club_id,
                'membership_type_id' => $membershipType->id,
                'pricing_rule_id' => $pricingRule?->id,
                'origin_membership_type_id' => null,
                'is_primary' => true,
                'is_billable' => true,
                'monthly_fee' => $monthlyFee,
                'monthly_fee_total' => $monthlyFee,
                'monthly_fee_share' => $monthlyFee,
                'billing_split_mode' => 'single',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => null,
                'status' => 'active',
            ]);
        });
    }

    public function withFiscalData(): static
    {
        return $this->afterCreating(function (MembershipAccount $account) {
            $holder = $account->primaryHolder()->with('member')->first()?->member;

            AccountFiscalData::query()->create([
                'membership_account_id' => $account->id,
                'fiscal_name' => strtoupper(Str::ascii($holder?->full_name ?? 'TITULAR DE PRUEBA')),
                'rfc' => 'XAXX010101' . strtoupper($this->faker->bothify('??#')),
                'cfdi_use' => 'G03',
                'fiscal_regime' => '612',
                'postal_code' => $account->club?->code === 'PE2' ? '72810' : '72500',
            ]);
        });
    }

    public function withLockers(): static
    {
        return $this->afterCreating(function (MembershipAccount $account) {
            $members = $account->accountMembers()->orderByDesc('is_primary_holder')->get();
            $categories = ['caballeros', 'damas', 'ninos', 'ninas'];

            foreach ($members as $index => $accountMember) {
                $category = $categories[$index % count($categories)];
                $lockerNumber = 80000 + ($account->id * 10) + $index;

                while (Locker::query()
                    ->where('club_id', $account->club_id)
                    ->where('category', $category)
                    ->where('number', $lockerNumber)
                    ->exists()) {
                    $lockerNumber++;
                }

                $locker = Locker::query()->create([
                    'club_id' => $account->club_id,
                    'number' => $lockerNumber,
                    'category' => $category,
                    'status' => 'ocupado',
                ]);

                LockerAssignment::query()->create([
                    'locker_id' => $locker->id,
                    'member_id' => $accountMember->member_id,
                    'club_id' => $account->club_id,
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->endOfYear()->toDateString(),
                    'amount_paid' => 500,
                    'year' => now()->year,
                ]);
            }
        });
    }

    private function relationshipId(string $name): ?int
    {
        return Relationship::query()->where('name', $name)->value('id');
    }
}
