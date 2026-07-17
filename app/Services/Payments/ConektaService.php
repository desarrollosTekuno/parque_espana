<?php

namespace App\Services\Payments;

use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\ConektaCustomer;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use Conekta\Api\CustomersApi;
use Conekta\Api\OrdersApi;
use Conekta\Api\PaymentMethodsApi;
use Conekta\Configuration;
use Conekta\Model\CreateCustomerPaymentMethodsRequest;
use Conekta\Model\Customer;
use Conekta\Model\OrderRequest;
use Conekta\Model\OrderRequestCustomerInfo;
use Conekta\Model\Product;
use Conekta\Model\ChargeRequest;
use Conekta\Model\ChargeRequestPaymentMethod;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConektaService
{
    /**
     * Clientes del SDK de Conekta ya construidos, indexados por club_id,
     * para no reconstruir el SDK en cada llamada dentro del mismo request.
     *
     * @var array<int, array{customers: CustomersApi, paymentMethods: PaymentMethodsApi, orders: OrdersApi}>
     */
    private array $clientsCache = [];

    // ──────────────────────────────────────────────────────────────
    // CLIENTES POR CLUB
    // ──────────────────────────────────────────────────────────────

    /**
     * Resuelve las credenciales Conekta del club (cada parque opera su propia
     * cuenta comercial) y construye/memoiza los clientes del SDK para ese club.
     * Si el club no tiene credenciales propias configuradas, cae en la llave
     * global de config/conekta.php.
     *
     * @return array{customers: CustomersApi, paymentMethods: PaymentMethodsApi, orders: OrdersApi}
     */
    private function clientsForClub(int $clubId): array
    {
        if (isset($this->clientsCache[$clubId])) {
            return $this->clientsCache[$clubId];
        }

        $clubPaymentMethod = ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->whereHas('paymentMethod', fn ($q) => $q->where('provider', PaymentMethod::PROVIDER_CONEKTA))
            ->first();

        $secretKey = $clubPaymentMethod?->conekta_secret_key ?: config('conekta.secret_key');

        $config = Configuration::getDefaultConfiguration()->setAccessToken($secretKey);
        $httpClient = new Client();

        return $this->clientsCache[$clubId] = [
            'customers'      => new CustomersApi($httpClient, $config),
            'paymentMethods' => new PaymentMethodsApi($httpClient, $config),
            'orders'         => new OrdersApi($httpClient, $config),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // CLIENTES
    // ──────────────────────────────────────────────────────────────

    /**
     * Obtiene o crea el cliente en Conekta para el miembro dado, dentro de la
     * cuenta Conekta del club indicado. Guarda el mapeo en
     * members.conekta_customers (un customer_id distinto por cada club en el
     * que el socio tenga cuenta, ya que cada club es una cuenta Conekta
     * independiente).
     */
    public function resolveCustomer(Member $member, int $clubId): string
    {
        $existing = ConektaCustomer::query()
            ->where('member_id', $member->id)
            ->where('club_id', $clubId)
            ->first();

        if ($existing) {
            return $existing->conekta_customer_id;
        }

        $customerData = new Customer([
            'name'  => trim("{$member->first_name} {$member->last_name} {$member->second_last_name}"),
            'email' => $member->email ?? "sin-correo-{$member->id}@parqueespana.local",
            'phone' => $member->phone ?? '+5200000000000',
        ]);

        $conektaCustomer = $this->clientsForClub($clubId)['customers']->createCustomer($customerData);
        $customerId      = $conektaCustomer->getId();

        ConektaCustomer::create([
            'member_id'           => $member->id,
            'club_id'             => $clubId,
            'conekta_customer_id' => $customerId,
        ]);

        return $customerId;
    }

    // ──────────────────────────────────────────────────────────────
    // FUENTES DE PAGO (TARJETAS)
    // ──────────────────────────────────────────────────────────────

    /**
     * Agrega una tarjeta tokenizada desde Flutter al perfil del miembro en Conekta
     * y la guarda localmente en members.payment_sources, asociada al club cuya
     * cuenta Conekta la tokenizó.
     *
     * @param  Member $member
     * @param  int    $clubId
     * @param  string $tokenId   Token generado por el SDK de Conekta en Flutter (tok_xxx)
     * @param  bool   $setDefault Marcarla como fuente predeterminada para ese club
     * @return MemberPaymentSource
     */
    public function addPaymentSource(Member $member, int $clubId, string $tokenId, bool $setDefault = false): MemberPaymentSource
    {
        return DB::transaction(function () use ($member, $clubId, $tokenId, $setDefault) {
            $customerId = $this->resolveCustomer($member, $clubId);

            $paymentMethodRequest = new CreateCustomerPaymentMethodsRequest([
                'type'     => 'card',
                'token_id' => $tokenId,
            ]);

            $conektaSource = $this->clientsForClub($clubId)['paymentMethods']->createCustomerPaymentMethods(
                $customerId,
                $paymentMethodRequest
            );

            if ($setDefault) {
                MemberPaymentSource::where('member_id', $member->id)
                    ->where('club_id', $clubId)
                    ->update(['is_default' => false]);
            }

            return MemberPaymentSource::create([
                'member_id'                  => $member->id,
                'club_id'                    => $clubId,
                'conekta_payment_source_id'  => $conektaSource->getId(),
                'card_type'                  => $conektaSource->getType() ?? null,
                'card_brand'                 => $conektaSource->getBrand() ?? null,
                'card_last4'                 => $conektaSource->getLast4() ?? null,
                'card_exp_month'             => $conektaSource->getExpMonth() ?? null,
                'card_exp_year'              => $conektaSource->getExpYear() ?? null,
                'cardholder_name'            => $conektaSource->getName() ?? null,
                'is_default'                 => $setDefault,
            ]);
        });
    }

    /**
     * Lista las fuentes de pago del miembro guardadas localmente para un club.
     */
    public function listPaymentSources(Member $member, int $clubId): \Illuminate\Database\Eloquent\Collection
    {
        return MemberPaymentSource::where('member_id', $member->id)
            ->where('club_id', $clubId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Elimina una fuente de pago en Conekta y en la BD local.
     */
    public function deletePaymentSource(Member $member, int $clubId, MemberPaymentSource $source): void
    {
        $customer = ConektaCustomer::query()
            ->where('member_id', $member->id)
            ->where('club_id', $clubId)
            ->first();

        if ($customer) {
            $this->clientsForClub($clubId)['paymentMethods']->deleteCustomerPaymentMethods(
                $customer->conekta_customer_id,
                $source->conekta_payment_source_id
            );
        }

        $source->delete();
    }

    /**
     * Marca una fuente de pago como predeterminada dentro de su club.
     */
    public function setDefaultPaymentSource(Member $member, int $clubId, MemberPaymentSource $source): void
    {
        MemberPaymentSource::where('member_id', $member->id)
            ->where('club_id', $clubId)
            ->update(['is_default' => false]);

        $source->update(['is_default' => true]);
    }

    // ──────────────────────────────────────────────────────────────
    // SPEI
    // ──────────────────────────────────────────────────────────────

    /**
     * Crea una orden SPEI en Conekta y devuelve el CLABE para que el socio
     * realice la transferencia desde su banco.
     *
     * El pago NO se confirma aquí — queda en estado "pending_payment".
     * La confirmación llega vía webhook (charge.paid).
     *
     * @param  Member $member
     * @param  int    $clubId
     * @param  int    $amountCents   Monto en centavos (ej. 250000 = $2,500.00 MXN)
     * @param  string $description   Concepto del cargo
     * @param  int    $expiresAt     Unix timestamp de expiración del CLABE
     * @param  array  $metadata      Datos adicionales que se mandan a Conekta
     * @return array  ['order_id', 'charge_id', 'clabe', 'bank', 'expires_at', 'status']
     */
    public function createSpeiOrder(
        Member $member,
        int    $clubId,
        int    $amountCents,
        string $description,
        int    $expiresAt,
        array  $metadata = []
    ): array {
        $customerId = $this->resolveCustomer($member, $clubId);

        $orderRequest = new OrderRequest([
            'currency'      => 'MXN',
            'customer_info' => new OrderRequestCustomerInfo([
                'customer_id' => $customerId,
            ]),
            'line_items' => [
                new Product([
                    'name'       => $description,
                    'unit_price' => $amountCents,
                    'quantity'   => 1,
                ]),
            ],
            'charges' => [
                new ChargeRequest([
                    'payment_method' => new ChargeRequestPaymentMethod([
                        'type' => 'spei',
                    ]),
                    'amount'     => $amountCents,
                    'expires_at' => $expiresAt,
                ]),
            ],
            'metadata' => $metadata,
        ]);

        $order  = $this->clientsForClub($clubId)['orders']->createOrder($orderRequest);
        $charge = $order->getCharges()->getData()[0] ?? null;

        // El SDK devuelve el payment_method del charge como objeto tipado.
        // Para SPEI, los campos CLABE y banco vienen en ese objeto.
        $paymentMethod = $charge?->getPaymentMethod();
        $clabe = null;
        $bank  = null;

        if ($paymentMethod) {
            // Intentamos acceder con los métodos tipados del SDK.
            // Si el SDK usa un modelo genérico, recurrimos a getClabe/getBank
            // según la versión de conekta-php instalada.
            $clabe = method_exists($paymentMethod, 'getClabe')
                ? $paymentMethod->getClabe()
                : ($paymentMethod->getReceivingAccountNumber() ?? null);

            $bank  = method_exists($paymentMethod, 'getBank')
                ? $paymentMethod->getBank()
                : ($paymentMethod->getReceivingAccountBank() ?? null);
        }

        return [
            'order_id'   => $order->getId(),
            'charge_id'  => $charge?->getId(),
            'clabe'      => $clabe,
            'bank'       => $bank,
            'expires_at' => $expiresAt,
            'status'     => $charge?->getStatus(), // "pending_payment"
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // COBROS
    // ──────────────────────────────────────────────────────────────

    /**
     * Crea un cargo (orden) en Conekta contra una fuente de pago guardada.
     *
     * @param  Member              $member
     * @param  MemberPaymentSource $source        Fuente de pago a cobrar (debe pertenecer a $clubId)
     * @param  int                 $clubId        Club/cuenta Conekta contra la que se cobra
     * @param  int                 $amountCents   Monto en centavos (ej. 50000 = $500.00 MXN)
     * @param  string              $description   Concepto del cargo
     * @param  array               $metadata      Datos adicionales (membership_id, charge_id, etc.)
     * @return array               ['order_id', 'charge_id', 'status', 'amount']
     */
    public function charge(
        Member $member,
        MemberPaymentSource $source,
        int $clubId,
        int $amountCents,
        string $description,
        array $metadata = []
    ): array {
        if ((int) $source->club_id !== $clubId) {
            throw new InvalidArgumentException(
                'La fuente de pago pertenece a un club distinto al de la cuenta Conekta contra la que se intenta cobrar.'
            );
        }

        $customerId = $this->resolveCustomer($member, $clubId);

        $orderRequest = new OrderRequest([
            'currency'      => 'MXN',
            'customer_info' => new OrderRequestCustomerInfo([
                'customer_id' => $customerId,
            ]),
            'line_items' => [
                new Product([
                    'name'       => $description,
                    'unit_price' => $amountCents,
                    'quantity'   => 1,
                ]),
            ],
            'charges' => [
                new ChargeRequest([
                    'payment_method' => new ChargeRequestPaymentMethod([
                        'type'             => 'card',
                        'payment_source_id' => $source->conekta_payment_source_id,
                    ]),
                    'amount' => $amountCents,
                ]),
            ],
            'metadata' => $metadata,
        ]);

        $order  = $this->clientsForClub($clubId)['orders']->createOrder($orderRequest);
        $charge = $order->getCharges()->getData()[0] ?? null;

        return [
            'order_id'  => $order->getId(),
            'charge_id' => $charge?->getId(),
            'status'    => $charge?->getStatus(),
            'amount'    => $amountCents,
        ];
    }
}
