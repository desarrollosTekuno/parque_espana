<?php

namespace Tests\Unit;

use App\Models\Administrator\Club;
use App\Models\Administrator\ClubAddress;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\State;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Services\Billing\PaymentTicketService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class PaymentTicketServiceTest extends TestCase
{
    public function test_it_builds_ticket_data_from_a_payment(): void
    {
        $country = (new Country())->forceFill(['name' => 'México']);
        $state = (new State())->forceFill(['name' => 'Puebla']);
        $city = (new City())->forceFill(['name' => 'Puebla']);

        $address = (new ClubAddress())->forceFill([
            'street' => '25 Oriente',
            'exterior_number' => '1001',
            'postal_code' => '72500',
        ]);
        $address->setRelation('country', $country);
        $address->setRelation('state', $state);
        $address->setRelation('city', $city);

        $club = (new Club())->forceFill([
            'code' => 'PE1',
            'name' => 'Parque España I',
            'legal_name' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA',
            'rfc' => 'FDP990423J51',
            'applies_iva' => true,
        ]);
        $club->id = 1;
        $club->setRelation('clubAddress', $address);

        $member = (new Member())->forceFill([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'second_last_name' => 'López',
        ]);

        $holder = new MembershipAccountMember();
        $holder->setRelation('member', $member);

        $account = (new MembershipAccount())->forceFill([
            'membership_number' => 'M-100',
            'internal_account_number' => 'I-100',
        ]);
        $account->setRelation('primaryHolder', $holder);

        $concept = (new ChargeConcept())->forceFill([
            'code' => 'MONTHLY_FEE',
            'name' => 'Mensualidad',
        ]);

        $charge = (new Charge())->forceFill(['description' => 'Mensualidad agosto']);
        $charge->id = 10;
        $charge->setRelation('concept', $concept);

        $application = (new PaymentApplication())->forceFill(['applied_amount' => 116]);
        $application->setRelation('charge', $charge);

        $paymentMethod = (new PaymentMethod())->forceFill([
            'code' => 'CASH',
            'name' => 'Efectivo',
        ]);

        $receiver = (new User())->forceFill([
            'name' => 'Antonio Cajero',
            'code' => 'CNF',
        ]);

        $payment = new class extends Payment
        {
            public function loadMissing($relations)
            {
                return $this;
            }
        };
        $payment->setRawAttributes([
            'club_id' => 1,
            'folio' => 'PE1-AUTO-260804-001',
            'amount' => 116,
            'paid_at' => Carbon::parse('2026-08-04 10:00:00'),
            'status' => 'registered',
            'metadata' => '[]',
        ]);
        $payment->id = 20;
        $payment->setRelation('club', $club);
        $payment->setRelation('paymentMethod', $paymentMethod);
        $payment->setRelation('receiver', $receiver);
        $payment->setRelation('membershipAccount', $account);
        $payment->setRelation('applications', new Collection([$application]));

        $data = (new PaymentTicketService())->data($payment);

        $this->assertSame('PE1-AUTO-260804-001', $data['folio']);
        $this->assertSame('A', $data['ticket_serie']);
        $this->assertSame('04001', $data['ticket_folio']);
        $this->assertSame('/assets/images/LogoP1.png', $data['club_logo_url']);
        $this->assertSame('FUNDACIÓN DEPORTIVO PARQUE ESPAÑA I', $data['club_nombre_institucion']);
        $this->assertSame('FUNDACIÓN DEPORTIVO PARQUE ESPAÑA', $data['club_razon_social']);
        $this->assertSame('FDP990423J51', $data['club_rfc']);
        $this->assertSame('CNF', $data['cajero_codigo']);
        $this->assertSame('Ana Pérez López', $data['titular']);
        $this->assertSame(['25 Oriente #1001', 'CP 72500', 'Puebla Puebla México'], $data['club_direccion_lineas']);
        $this->assertSame(100.0, $data['subtotal']);
        $this->assertSame(16.0, $data['iva']);
        $this->assertSame('Mensualidad agosto', $data['conceptos'][0]['descripcion']);
        $this->assertSame(116.0, $data['conceptos'][0]['monto']);
    }
}
