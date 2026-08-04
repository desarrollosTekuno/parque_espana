<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Billing\Payment;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentTicketTestSeeder extends Seeder
{
    public function run(): void
    {
        $clubId = env('PAYMENT_TEST_CLUB_ID');
        $requestedPayments = max(1, (int) env('PAYMENT_TEST_COUNT', 15));
        $clubs = Club::query()
            ->when($clubId, function ($query) use ($clubId) {
                $query->where('id', $clubId);
            })
            ->whereIn('code', ['PE1', 'PE2'])
            ->orderBy('id')
            ->get();

        if ($clubs->isEmpty()) {
            throw new RuntimeException('No se encontraron parques para generar cuentas y pagos.');
        }

        $cashier = User::query()->updateOrCreate(
            ['email' => 'ticket.cashier@example.test'],
            [
                'name' => 'Cajero de Pruebas',
                'code' => 'CDP',
                'password' => 'password',
            ]
        );

        $accounts = collect();

        DB::transaction(function () use ($clubs, $cashier, $requestedPayments, $accounts) {
            foreach ($clubs as $club) {
                $accounts->push(
                    MembershipAccount::factory()
                        ->forClub($club)
                        ->individual()
                        ->withMembers()
                        ->withFiscalData()
                        ->withLockers()
                        ->create()
                );

                $accounts->push(
                    MembershipAccount::factory()
                        ->forClub($club)
                        ->family()
                        ->withMembers(2)
                        ->withFiscalData()
                        ->withLockers()
                        ->create()
                );
            }

            $paymentMethods = ['CASH', 'DEBIT_CARD', 'BANK_TRANSFER', 'CHECK'];
            $paymentCount = max($requestedPayments, $accounts->count());

            for ($index = 0; $index < $paymentCount; $index++) {
                $account = $accounts[$index % $accounts->count()];
                $paymentMethod = $paymentMethods[$index % count($paymentMethods)];

                Payment::factory()
                    ->forAccount($account)
                    ->usingPaymentMethod($paymentMethod)
                    ->state([
                        'received_by' => $cashier->id,
                    ])
                    ->withTicketConcept()
                    ->withTestFolio()
                    ->create();
            }
        });

        $this->command?->info(
            "Se generaron {$accounts->count()} cuentas completas y "
            . max($requestedPayments, $accounts->count())
            . ' pagos para probar tickets.'
        );
    }
}
