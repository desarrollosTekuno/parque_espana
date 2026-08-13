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
use App\Models\Members\Locker;
use App\Models\Members\LockerAssignment;
use App\Models\Members\Member;
use App\Models\Memberships\AccountFiscalData;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\User;
use App\Services\Billing\PaymentTicketService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class PaymentTicketServiceTest extends TestCase
{
    public function test_it_builds_ticket_data_from_a_payment(): void
    {
        $country = (new Country)->forceFill(['name' => 'México']);
        $state = (new State)->forceFill(['name' => 'Puebla']);
        $city = (new City)->forceFill(['name' => 'Puebla']);

        $address = (new ClubAddress)->forceFill([
            'street' => '25 Oriente',
            'exterior_number' => '1001',
            'postal_code' => '72500',
        ]);
        $address->setRelation('country', $country);
        $address->setRelation('state', $state);
        $address->setRelation('city', $city);

        $club = (new Club)->forceFill([
            'code' => 'PE1',
            'name' => 'Parque España I',
            'legal_name' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA',
            'rfc' => 'FDP990423J51',
            'billing_url' => 'http://www.parqueespana2.com.mx',
            'applies_iva' => true,
        ]);
        $club->id = 1;
        $club->setRelation('clubAddress', $address);

        $member = (new Member)->forceFill([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'second_last_name' => 'López',
        ]);

        $holder = new MembershipAccountMember;
        $holder->setRelation('member', $member);

        $account = (new MembershipAccount)->forceFill([
            'account_group_id' => 1,
            'club_id' => 1,
            'membership_number' => 'M-100',
            'internal_account_number' => 'I-100',
        ]);
        $account->id = 1;
        $account->setRelation('primaryHolder', $holder);
        $account->setRelation('fiscalData', (new AccountFiscalData)->forceFill([
            'fiscal_name' => 'ANA PEREZ LOPEZ',
            'rfc' => 'PELJ900101ABC',
            'cfdi_use' => 'G03',
            'fiscal_regime' => '612',
            'postal_code' => '72500',
        ]));

        $secondAccount = (new MembershipAccount)->forceFill([
            'account_group_id' => 1,
            'club_id' => 2,
            'membership_number' => 'M-200',
            'internal_account_number' => 'I-200',
        ]);
        $secondAccount->id = 2;

        $menLocker = (new Locker)->forceFill([
            'number' => 4,
            'category' => 'caballeros',
        ]);
        $womenLocker = (new Locker)->forceFill([
            'number' => 862,
            'category' => 'damas',
        ]);
        $menAssignment = (new LockerAssignment)->forceFill([
            'club_id' => 1,
            'locker_id' => 1,
        ]);
        $menAssignment->id = 1;
        $menAssignment->setRelation('locker', $menLocker);
        $womenAssignment = (new LockerAssignment)->forceFill([
            'club_id' => 2,
            'locker_id' => 2,
        ]);
        $womenAssignment->id = 2;
        $womenAssignment->setRelation('locker', $womenLocker);
        $account->setRelation('currentLockerAssignments', new Collection([$menAssignment]));
        $secondAccount->setRelation('currentLockerAssignments', new Collection([$womenAssignment]));

        $accountGroup = new MembershipAccountGroup;
        $accountGroup->id = 1;
        $accountGroup->setRelation('accounts', new Collection([$account, $secondAccount]));
        $account->setRelation('accountGroup', $accountGroup);

        $concept = (new ChargeConcept)->forceFill([
            'code' => 'MONTHLY_FEE',
            'name' => 'Mensualidad',
        ]);

        $charge = (new Charge)->forceFill(['description' => 'Mensualidad agosto']);
        $charge->id = 10;
        $charge->setRelation('concept', $concept);
        $charge->setRelation('membership', null);

        $application = (new PaymentApplication)->forceFill([
            'applied_amount' => 116,
            'subtotal' => 100,
            'iva' => 16,
        ]);
        $application->setRelation('charge', $charge);

        $paymentMethod = (new PaymentMethod)->forceFill([
            'code' => 'DEBIT_CARD',
            'name' => 'Tarjeta de dÃ©bito',
        ]);

        $receiver = (new User)->forceFill([
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
            'reference' => '1215121412',
            'bank_name' => 'VISA',
        ]);
        $payment->id = 20;
        $payment->setRelation('club', $club);
        $payment->setRelation('paymentMethod', $paymentMethod);
        $payment->setRelation('receiver', $receiver);
        $payment->setRelation('membershipAccount', $account);
        $payment->setRelation('applications', new Collection([$application]));

        $data = (new PaymentTicketService)->data($payment);

        $this->assertSame('PE1-AUTO-260804-001', $data['folio']);
        $this->assertSame('A', $data['ticket_serie']);
        $this->assertSame('04001', $data['ticket_folio']);
        $this->assertMatchesRegularExpression('/^0000000100DPAA04001[A-Z0-9]{9}$/', $data['identificacion_archivo']);
        $this->assertSame('/assets/images/LogoP1.png', $data['club_logo_url']);
        $this->assertSame('FUNDACIÓN DEPORTIVO PARQUE ESPAÑA I', $data['club_nombre_institucion']);
        $this->assertSame('FUNDACIÓN DEPORTIVO PARQUE ESPAÑA', $data['club_razon_social']);
        $this->assertSame('FDP990423J51', $data['club_rfc']);
        $this->assertSame('http://www.parqueespana2.com.mx', $data['club_url_facturacion']);
        $this->assertSame('CNF', $data['cajero_codigo']);
        $this->assertSame('M-100', $data['cuenta_numero']);
        $this->assertSame('ANA PEREZ LOPEZ', $data['receptor_nombre']);
        $this->assertSame('PELJ900101ABC', $data['receptor_rfc']);
        $this->assertSame('G03', $data['receptor_uso_cfdi']);
        $this->assertSame('612', $data['receptor_regimen_fiscal']);
        $this->assertSame('72500', $data['receptor_codigo_postal']);
        $this->assertSame(['CA00004'], $data['casilleros']);
        $this->assertSame('Ana Pérez López', $data['titular']);
        $this->assertSame(['25 Oriente #1001', 'CP 72500', 'Puebla Puebla México'], $data['club_direccion_lineas']);
        $this->assertSame(100.0, $data['subtotal']);
        $this->assertSame(16.0, $data['iva']);
        $this->assertSame(116.0, $data['total']);
        $this->assertSame('TD', $data['forma_pago_ticket_codigo']);
        $this->assertSame('1215121412', $data['pago_identificacion']);
        $this->assertSame('VISA', $data['banco']);
        $this->assertSame('DOS MESES SIN APORTACIÓN GENERAN SUSPENSIÓN', $data['leyenda_institucion']);
        $this->assertSame('Este comprobante no tiene validez fiscal.', $data['leyenda_no_fiscal']);
        $this->assertSame('Mensualidad agosto', $data['conceptos'][0]['descripcion']);
        $this->assertSame(1, $data['conceptos'][0]['cantidad']);
        $this->assertSame(100.0, $data['conceptos'][0]['importe_unitario']);
        $this->assertSame(100.0, $data['conceptos'][0]['total']);
        $this->assertNull($data['conceptos'][0]['descuento']);
        $this->assertSame(116.0, $data['conceptos'][0]['monto']);

        $club->applies_iva = false;
        $naturalData = (new PaymentTicketService)->data($payment);

        $this->assertSame(100.0, $naturalData['subtotal']);
        $this->assertSame($data['identificacion_archivo'], $naturalData['identificacion_archivo']);
        $this->assertSame(16.0, $naturalData['iva']);
        $this->assertSame(16, $naturalData['iva_porcentaje']);
        $this->assertSame(116.0, $naturalData['total']);
        $this->assertSame(100.0, $naturalData['conceptos'][0]['importe_unitario']);
    }

    public function test_it_builds_one_ticket_per_park_for_an_interclub_monthly_fee(): void
    {
        $clubOne = (new Club)->forceFill(['code' => 'PE1', 'name' => 'Parque España I']);
        $clubOne->id = 1;
        $clubOne->setRelation('clubAddress', null);

        $clubTwo = (new Club)->forceFill(['code' => 'PE2', 'name' => 'Parque España II']);
        $clubTwo->id = 2;
        $clubTwo->setRelation('clubAddress', null);

        $member = (new Member)->forceFill(['first_name' => 'Ana', 'last_name' => 'Pérez']);
        $holderOne = (new MembershipAccountMember)->setRelation('member', $member);
        $holderTwo = (new MembershipAccountMember)->setRelation('member', $member);

        $accountOne = (new MembershipAccount)->forceFill([
            'account_group_id' => 1,
            'club_id' => 1,
            'membership_number' => 'P1-100',
        ]);
        $accountOne->id = 1;
        $accountOne->setRelation('club', $clubOne);
        $accountOne->setRelation('primaryHolder', $holderOne);
        $accountOne->setRelation('fiscalData', null);
        $accountOne->setRelation('currentLockerAssignments', new Collection);

        $accountTwo = (new MembershipAccount)->forceFill([
            'account_group_id' => 1,
            'club_id' => 2,
            'membership_number' => 'P2-200',
        ]);
        $accountTwo->id = 2;
        $accountTwo->setRelation('club', $clubTwo);
        $accountTwo->setRelation('primaryHolder', $holderTwo);
        $accountTwo->setRelation('fiscalData', null);
        $accountTwo->setRelation('currentLockerAssignments', new Collection);

        $membershipOne = (new Membership)->forceFill([
            'membership_account_id' => 1,
            'club_id' => 1,
            'is_primary' => true,
            'status' => 'active',
            'interclub_package_rule_id' => 1,
        ]);
        $membershipOne->setRelation('club', $clubOne);
        $membershipOne->setRelation('account', $accountOne);
        $membershipOne->setRelation('pricingRule', null);

        $membershipTwo = (new Membership)->forceFill([
            'membership_account_id' => 2,
            'club_id' => 2,
            'is_primary' => true,
            'status' => 'active',
        ]);
        $membershipTwo->setRelation('club', $clubTwo);

        $accountOne->setRelation('memberships', new Collection([$membershipOne]));
        $accountTwo->setRelation('memberships', new Collection([$membershipTwo]));

        $accountGroup = new MembershipAccountGroup;
        $accountGroup->id = 1;
        $accountGroup->setRelation('accounts', new Collection([$accountOne, $accountTwo]));
        $accountOne->setRelation('accountGroup', $accountGroup);

        $concept = (new ChargeConcept)->forceFill(['code' => 'MONTHLY_FEE', 'name' => 'Mensualidad']);
        $charge = (new Charge)->forceFill(['description' => 'Mensualidad agosto']);
        $charge->id = 10;
        $charge->setRelation('concept', $concept);
        $charge->setRelation('membership', $membershipOne);

        $application = (new PaymentApplication)->forceFill([
            'applied_amount' => 116,
            'subtotal' => 100,
            'iva' => 16,
        ]);
        $application->setRelation('charge', $charge);

        $paymentMethod = (new PaymentMethod)->forceFill(['code' => 'CASH', 'name' => 'Efectivo']);
        $receiver = (new User)->forceFill(['name' => 'Antonio Cajero', 'code' => 'AC']);

        $payment = new class extends Payment
        {
            public function loadMissing($relations)
            {
                return $this;
            }
        };
        $payment->setRawAttributes([
            'club_id' => 1,
            'folio' => 'PE1-AUTO-260813-001',
            'amount' => 116,
            'paid_at' => Carbon::parse('2026-08-13 10:00:00'),
            'status' => 'registered',
            'metadata' => '[]',
        ]);
        $payment->id = 20;
        $payment->setRelation('club', $clubOne);
        $payment->setRelation('paymentMethod', $paymentMethod);
        $payment->setRelation('receiver', $receiver);
        $payment->setRelation('membershipAccount', $accountOne);
        $payment->setRelation('applications', new Collection([$application]));

        $tickets = (new PaymentTicketService)->tickets($payment);

        $this->assertCount(2, $tickets);
        $this->assertSame([1, 2], array_column($tickets, 'club_id'));
        $this->assertSame(['P1-100', 'P2-200'], array_column($tickets, 'cuenta_numero'));
        $this->assertSame([50.0, 50.0], array_column($tickets, 'subtotal'));
        $this->assertSame([8.0, 8.0], array_column($tickets, 'iva'));
        $this->assertSame([58.0, 58.0], array_column($tickets, 'total'));
        $this->assertSame(58.0, $tickets[0]['formas_de_pago'][0]['monto']);
        $this->assertSame(58.0, $tickets[1]['formas_de_pago'][0]['monto']);
    }
}
