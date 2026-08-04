<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Billing\Payment;
use App\Models\Members\Member;
use App\Models\Memberships\AccountFiscalData;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentTicketTestSeeder extends Seeder
{
    public function run(): void
    {
        $clubId = env('PAYMENT_TEST_CLUB_ID');
        $count = max(1, (int) env('PAYMENT_TEST_COUNT', 15));

        $club = Club::query()
            ->when($clubId, function ($query) use ($clubId) {
                $query->where('id', $clubId);
            })
            ->orderByRaw("CASE WHEN code = 'PE1' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (!$club) {
            throw new RuntimeException('No se encontró un club para generar pagos.');
        }

        DB::transaction(function () use ($club, $count) {
            $this->createTestAccount($club);

            Payment::factory()
                ->count($count)
                ->forClub($club->id)
                ->withTicketConcept()
                ->withTestFolio()
                ->create();
        });

        $this->command?->info("Se generaron {$count} pagos de prueba para {$club->name}.");
    }

    private function createTestAccount(Club $club): void
    {
        $membershipNumber = 'TEST-TICKET-' . $club->code;
        $account = MembershipAccount::query()
            ->where('membership_number', $membershipNumber)
            ->first();

        if (!$account) {
            $group = MembershipAccountGroup::query()->create([
                'status' => 'active',
            ]);

            $account = MembershipAccount::query()->create([
                'account_group_id' => $group->id,
                'club_id' => $club->id,
                'membership_number' => $membershipNumber,
                'internal_account_number' => $membershipNumber,
                'account_type' => 'individual',
                'status' => 'active',
            ]);
        }

        $member = Member::query()->updateOrCreate(
            ['email' => 'ticket.' . strtolower($club->code) . '@example.test'],
            [
                'first_name' => 'Socio',
                'last_name' => 'Prueba',
                'second_last_name' => 'Tickets',
                'birthdate' => '1990-01-01',
                'phone' => '5550000000',
            ]
        );

        MembershipAccountMember::query()->updateOrCreate(
            [
                'membership_account_id' => $account->id,
                'member_id' => $member->id,
            ],
            [
                'relationship_id' => null,
                'is_primary_holder' => true,
            ]
        );

        AccountFiscalData::query()->updateOrCreate(
            ['membership_account_id' => $account->id],
            [
                'fiscal_name' => 'SOCIO PRUEBA TICKETS',
                'rfc' => 'XAXX010101000',
                'cfdi_use' => 'G03',
                'fiscal_regime' => '612',
                'postal_code' => '72500',
            ]
        );
    }
}
