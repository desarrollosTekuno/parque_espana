<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PaymentApiSeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::query()
            ->where('code', 'PE1')
            ->first();

        if (!$club) {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'antoniotoxquisosa@hotmail.com'],
            [
                'name' => 'Administrador del Club',
                'password' => bcrypt('Pa$$w0rd'),
            ]
        );

        $member = Member::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => 'Socio',
                'last_name' => 'Pagos',
                'second_last_name' => 'App',
                'birthdate' => '1990-01-01',
                'phone' => '2221234567',
                'email' => $user->email,
            ]
        );

        $membership = $this->resolveMembership($member, $club);

        if (!$membership) {
            return;
        }

        $account = $membership->account;

        $monthlyFeeConcept = ChargeConcept::query()->where('code', 'MONTHLY_FEE')->first();
        $inscriptionConcept = ChargeConcept::query()->where('code', 'INSCRIPTION')->first();
        $paymentMethod = PaymentMethod::query()->where('code', 'APP_PAYMENT')->first();

        if (!$monthlyFeeConcept || !$inscriptionConcept || !$paymentMethod) {
            return;
        }

        $year = now()->year;
        $currentMonth = now()->month;

        $janCharge = $this->upsertCharge([
            'membership_account_id' => $account->id,
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'concept_id' => $monthlyFeeConcept->id,
            'description' => 'Mensualidad enero app movil',
            'amount' => 1200,
            'balance' => 0,
            'issue_date' => Carbon::create($year, 1, 1)->toDateString(),
            'due_date' => Carbon::create($year, 1, 10)->toDateString(),
            'period_year' => $year,
            'period_month' => 1,
            'allows_partial_payments' => false,
            'status' => 'paid',
        ]);

        $febCharge = $this->upsertCharge([
            'membership_account_id' => $account->id,
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'concept_id' => $monthlyFeeConcept->id,
            'description' => 'Mensualidad febrero app movil',
            'amount' => 1200,
            'balance' => 1200,
            'issue_date' => Carbon::create($year, 2, 1)->toDateString(),
            'due_date' => Carbon::create($year, 2, 10)->toDateString(),
            'period_year' => $year,
            'period_month' => 2,
            'allows_partial_payments' => false,
            'status' => 'pending',
        ]);

        $marCharge = $this->upsertCharge([
            'membership_account_id' => $account->id,
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'concept_id' => $monthlyFeeConcept->id,
            'description' => 'Mensualidad marzo app movil',
            'amount' => 1200,
            'balance' => 600,
            'issue_date' => Carbon::create($year, 3, 1)->toDateString(),
            'due_date' => Carbon::create($year, 3, 10)->toDateString(),
            'period_year' => $year,
            'period_month' => 3,
            'allows_partial_payments' => false,
            'status' => 'partial',
        ]);

        $aprCharge = $this->upsertCharge([
            'membership_account_id' => $account->id,
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'concept_id' => $monthlyFeeConcept->id,
            'description' => 'Mensualidad abril app movil',
            'amount' => 1200,
            'balance' => 0,
            'issue_date' => Carbon::create($year, 4, 1)->toDateString(),
            'due_date' => Carbon::create($year, 4, 10)->toDateString(),
            'period_year' => $year,
            'period_month' => 4,
            'allows_partial_payments' => false,
            'status' => 'paid',
        ]);

        $inscriptionCharge = $this->upsertCharge([
            'membership_account_id' => $account->id,
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'concept_id' => $inscriptionConcept->id,
            'description' => 'Inscripcion app movil',
            'amount' => 3500,
            'balance' => 3500,
            'issue_date' => Carbon::create($year, $currentMonth, 1)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_year' => null,
            'period_month' => null,
            'allows_partial_payments' => true,
            'status' => 'pending',
        ]);

        $payments = [
            [
                'reference' => "PAY-API-{$year}-001",
                'amount' => 600,
                'paid_at' => Carbon::create($year, 1, 5, 10, 0, 0),
                'charge' => $janCharge,
                'applied_amount' => 600,
            ],
            [
                'reference' => "PAY-API-{$year}-002",
                'amount' => 600,
                'paid_at' => Carbon::create($year, 1, 10, 18, 0, 0),
                'charge' => $janCharge,
                'applied_amount' => 600,
            ],
            [
                'reference' => "PAY-API-{$year}-003",
                'amount' => 300,
                'paid_at' => Carbon::create($year, 3, 5, 11, 0, 0),
                'charge' => $marCharge,
                'applied_amount' => 300,
            ],
            [
                'reference' => "PAY-API-{$year}-004",
                'amount' => 300,
                'paid_at' => Carbon::create($year, 3, 20, 16, 0, 0),
                'charge' => $marCharge,
                'applied_amount' => 300,
            ],
            [
                'reference' => "PAY-API-{$year}-005",
                'amount' => 700,
                'paid_at' => Carbon::create($year, 4, 5, 9, 0, 0),
                'charge' => $aprCharge,
                'applied_amount' => 700,
            ],
            [
                'reference' => "PAY-API-{$year}-006",
                'amount' => 500,
                'paid_at' => Carbon::create($year, 4, 15, 17, 30, 0),
                'charge' => $aprCharge,
                'applied_amount' => 500,
            ],
        ];

        foreach ($payments as $paymentData) {
            $payment = Payment::query()->updateOrCreate(
                ['reference' => $paymentData['reference']],
                [
                    'membership_account_id' => $account->id,
                    'club_id' => $club->id,
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => $paymentData['amount'],
                    'paid_at' => $paymentData['paid_at'],
                    'bank_name' => null,
                    'check_number' => null,
                    'notes' => 'Pago de prueba para APIs moviles',
                    'received_by' => $user->id,
                    'status' => 'registered',
                    'metadata' => [
                        'source' => 'payment-api-seeder',
                    ],
                ]
            );

            PaymentApplication::query()->updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'charge_id' => $paymentData['charge']->id,
                ],
                [
                    'applied_amount' => $paymentData['applied_amount'],
                ]
            );
        }

        $inscriptionCharge->refresh();
        $febCharge->refresh();
        $marCharge->refresh();
        $aprCharge->refresh();
    }

    private function resolveMembership(Member $member, Club $club): ?Membership
    {
        $membership = Membership::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('account.accountMembers', function ($query) use ($member) {
                $query->where('member_id', $member->id)
                    ->where('is_primary_holder', true);
            })
            ->first();

        if ($membership) {
            return $membership;
        }

        $membershipType = MembershipType::query()
            ->where('club_id', $club->id)
            ->where('allows_multiple_members', false)
            ->orderBy('id')
            ->first();

        if (!$membershipType) {
            return null;
        }

        $group = MembershipAccountGroup::query()->create([
            'status' => 'active',
        ]);

        $account = MembershipAccount::query()->create([
            'account_group_id' => $group->id,
            'club_id' => $club->id,
            'membership_number' => 'PAY-API-' . $club->code . '-' . str_pad((string) $member->id, 6, '0', STR_PAD_LEFT),
            'internal_account_number' => 'PAYAPI-' . $club->code . '-' . str_pad((string) $member->id, 6, '0', STR_PAD_LEFT),
            'account_type' => 'individual',
            'status' => 'active',
        ]);

        MembershipAccountMember::query()->firstOrCreate(
            [
                'membership_account_id' => $account->id,
                'member_id' => $member->id,
            ],
            [
                'relationship_id' => null,
                'is_primary_holder' => true,
            ]
        );

        return Membership::query()->create([
            'membership_account_id' => $account->id,
            'club_id' => $club->id,
            'membership_type_id' => $membershipType->id,
            'origin_membership_type_id' => null,
            'is_primary' => true,
            'is_billable' => true,
            'monthly_fee' => 1200,
            'monthly_fee_total' => 1200,
            'monthly_fee_share' => 1200,
            'billing_split_mode' => 'single',
            'start_date' => Carbon::create(now()->year, 1, 1)->toDateString(),
            'end_date' => null,
            'status' => 'active',
        ]);
    }

    private function upsertCharge(array $data): Charge
    {
        return Charge::query()->updateOrCreate(
            [
                'membership_account_id' => $data['membership_account_id'],
                'concept_id' => $data['concept_id'],
                'description' => $data['description'],
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'],
            ],
            [
                'membership_id' => $data['membership_id'],
                'member_id' => $data['member_id'],
                'amount' => $data['amount'],
                'balance' => $data['balance'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'allows_partial_payments' => $data['allows_partial_payments'],
                'status' => $data['status'],
                'metadata' => [
                    'source' => 'payment-api-seeder',
                ],
            ]
        );
    }
}
