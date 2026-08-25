<?php

namespace Database\Factories;

use App\Models\Administrator\Club;
use App\Models\Catalogs\Relationship;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Members\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        $firstNames = [
            'Armando',
            'Carlos V',
            'Dolores',
            'Socorro',
            'Amparo',
            'Ascencio',
            'Aquilino',
            'Benito',
            'Eulalio',
            'Inocencio',
            'Filemón',
            'Anastasio',
            'Prudencio',
            'Perfecto',
            'Nicanor',
            'Crispín',
            'Genaro',
            'Gumaro',
            'Teófilo',
            'Heriberto',
        ];

        $lastNames = [
            'III',
            'Prieto',
            'Mojica',
            'Camacho',
            'Cordero',
            'Barriga',
            'Trejo',
            'Lechuga',
            'Bonilla',
            'Cansino',
            'Paniagua',
            'Carrillo',
            'Cabezas',
            'Mejía',
            'Pecho',
            'Patiño',
            'Palacios',
            'Verdugo',
            'Mondragón',
            'Zamarripa',
        ];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $secondLastName = $lastNames[array_rand($lastNames)];
        $suffix = str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $birthdate = now()->subYears(random_int(18, 65))->subDays(random_int(0, 365))->toDateString();
        $phone = '555' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);

        return [
            'first_name' => Str::title($firstName),
            'last_name' => Str::title($lastName),
            'second_last_name' => Str::title($secondLastName),
            'birthdate' => $birthdate,
            'phone' => $phone,
            'email' => "{$firstName}.{$lastName}{$suffix}@mail.com",
        ];
    }

    public function individualWithUser(): static
    {
        return $this->afterCreating(function (Member $member) {
            $email = $member->email ?: 'miembro' . $member->id . '@mail.com';

            $user = User::create([
                'name' => trim("{$member->first_name} {$member->last_name}"),
                'email' => $email,
                'password' => Hash::make('1234'),
            ]);

            $member->update([
                'user_id' => $user->id,
                'email' => $email,
            ]);
        });
    }

    public function individualWithUserAndMembership(?int $clubId = null): static
    {
        return $this->individualWithUser()
            ->afterCreating(function (Member $member) use ($clubId) {
                $club = $clubId
                    ? Club::find($clubId)
                    : Club::query()->inRandomOrder()->first();

                if (!$club) {
                    return;
                }

                $membershipType = MembershipType::query()
                    ->where('club_id', $club->id)
                    ->where('allows_multiple_members', false)
                    ->orderBy('id')
                    ->first();

                if (!$membershipType) {
                    return;
                }

                $relationship = Relationship::query()
                    ->whereRaw('LOWER(name) = ?', ['titular'])
                    ->first();

                $group = MembershipAccountGroup::create([
                    'status' => 'active',
                ]);

                $membershipAccount = MembershipAccount::create([
                    'account_group_id' => $group->id,
                    'club_id' => $club->id,
                    'membership_number' => 'M-' . str_pad((string) $member->id, 6, '0', STR_PAD_LEFT),
                    'account_type' => 'individual',
                    'status' => 'active',
                ]);

                MembershipAccountMember::create([
                    'membership_account_id' => $membershipAccount->id,
                    'member_id' => $member->id,
                    'relationship_id' => $relationship?->id,
                    'is_primary_holder' => true,
                ]);

                Membership::create([
                    'membership_account_id' => $membershipAccount->id,
                    'club_id' => $club->id,
                    'membership_type_id' => $membershipType->id,
                    'origin_membership_type_id' => null,
                    'is_primary' => true,
                    'is_billable' => true,
                    'monthly_fee' => 1000,
                    'monthly_fee_total' => 1000,
                    'monthly_fee_share' => 1000,
                    'billing_split_mode' => 'single',
                    'start_date' => now()->toDateString(),
                    'end_date' => null,
                    'status' => 'active',
                ]);
            });
    }
}
