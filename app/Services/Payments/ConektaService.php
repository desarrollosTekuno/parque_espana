<?php

namespace App\Services\Payments;

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
use Illuminate\Support\Facades\Log;

class ConektaService
{
    private CustomersApi $customersApi;
    private PaymentMethodsApi $paymentMethodsApi;
    private OrdersApi $ordersApi;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken(config('conekta.secret_key'));

        $httpClient = new Client();

        $this->customersApi    = new CustomersApi($httpClient, $config);
        $this->paymentMethodsApi = new PaymentMethodsApi($httpClient, $config);
        $this->ordersApi       = new OrdersApi($httpClient, $config);
    }

    // ──────────────────────────────────────────────────────────────
    // CLIENTES
    // ──────────────────────────────────────────────────────────────

    /**
     * Obtiene o crea el cliente en Conekta para el miembro dado.
     * Guarda el conekta_customer_id en la tabla members.members.
     */
    public function resolveCustomer(Member $member): string
    {
        if ($member->conekta_customer_id) {
            return $member->conekta_customer_id;
        }

        $customerData = new Customer([
            'name'  => trim("{$member->first_name} {$member->last_name} {$member->second_last_name}"),
            'email' => $member->email ?? "sin-correo-{$member->id}@parqueespana.local",
            'phone' => $member->phone ?? '+5200000000000',
        ]);

        $conektaCustomer = $this->customersApi->createCustomer($customerData);
        $customerId      = $conektaCustomer->getId();

        $member->update(['conekta_customer_id' => $customerId]);

        return $customerId;
    }

    // ──────────────────────────────────────────────────────────────
    // FUENTES DE PAGO (TARJETAS)
    // ──────────────────────────────────────────────────────────────

    /**
     * Agrega una tarjeta tokenizada desde Flutter al perfil del miembro en Conekta
     * y la guarda localmente en members.payment_sources.
     *
     * @param  Member $member
     * @param  string $tokenId   Token generado por el SDK de Conekta en Flutter (tok_xxx)
     * @param  bool   $setDefault Marcarla como fuente predeterminada
     * @return MemberPaymentSource
     */
    public function addPaymentSource(Member $member, string $tokenId, bool $setDefault = false): MemberPaymentSource
    {
        return DB::transaction(function () use ($member, $tokenId, $setDefault) {
            $customerId = $this->resolveCustomer($member);

            $paymentMethodRequest = new CreateCustomerPaymentMethodsRequest([
                'type'     => 'card',
                'token_id' => $tokenId,
            ]);

            $conektaSource = $this->paymentMethodsApi->createCustomerPaymentMethods(
                $customerId,
                $paymentMethodRequest
            );

            if ($setDefault) {
                MemberPaymentSource::where('member_id', $member->id)->update(['is_default' => false]);
            }

            $source = MemberPaymentSource::create([
                'member_id'                  => $member->id,
                'conekta_payment_source_id'  => $conektaSource->getId(),
                'card_type'                  => $conektaSource->getType() ?? null,
                'card_brand'                 => $conektaSource->getBrand() ?? null,
                'card_last4'                 => $conektaSource->getLast4() ?? null,
                'card_exp_month'             => $conektaSource->getExpMonth() ?? null,
                'card_exp_year'              => $conektaSource->getExpYear() ?? null,
                'cardholder_name'            => $conektaSource->getName() ?? null,
                'is_default'                 => $setDefault,
            ]);

            return $source;
        });
    }

    /**
     * Lista las fuentes de pago del miembro guardadas localmente.
     */
    public function listPaymentSources(Member $member): \Illuminate\Database\Eloquent\Collection
    {
        return MemberPaymentSource::where('member_id', $member->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Elimina una fuente de pago en Conekta y en la BD local.
     */
    public function deletePaymentSource(Member $member, MemberPaymentSource $source): void
    {
        $customerId = $member->conekta_customer_id;

        if ($customerId) {
            $this->paymentMethodsApi->deleteCustomerPaymentMethods(
                $customerId,
                $source->conekta_payment_source_id
            );
        }

        $source->delete();
    }

    /**
     * Marca una fuente de pago como predeterminada.
     */
    public function setDefaultPaymentSource(Member $member, MemberPaymentSource $source): void
    {
        MemberPaymentSource::where('member_id', $member->id)->update(['is_default' => false]);
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
     * @param  int    $amountCents   Monto en centavos (ej. 250000 = $2,500.00 MXN)
     * @param  string $description   Concepto del cargo
     * @param  int    $expiresAt     Unix timestamp de expiración del CLABE
     * @param  array  $metadata      Datos adicionales que se mandan a Conekta
     * @return array  ['order_id', 'charge_id', 'clabe', 'bank', 'expires_at', 'status']
     */
    public function createSpeiOrder(
        Member $member,
        int    $amountCents,
        string $description,
        int    $expiresAt,
        array  $metadata = []
    ): array {
        $customerId = $this->resolveCustomer($member);

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

        $order  = $this->ordersApi->createOrder($orderRequest);
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
     * @param  MemberPaymentSource $source        Fuente de pago a cobrar
     * @param  int                 $amountCents   Monto en centavos (ej. 50000 = $500.00 MXN)
     * @param  string              $description   Concepto del cargo
     * @param  array               $metadata      Datos adicionales (membership_id, charge_id, etc.)
     * @return array               ['order_id', 'charge_id', 'status', 'amount']
     */
    public function charge(
        Member $member,
        MemberPaymentSource $source,
        int $amountCents,
        string $description,
        array $metadata = []
    ): array {
        $customerId = $this->resolveCustomer($member);

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

        $order  = $this->ordersApi->createOrder($orderRequest);
        $charge = $order->getCharges()->getData()[0] ?? null;

        return [
            'order_id'  => $order->getId(),
            'charge_id' => $charge?->getId(),
            'status'    => $charge?->getStatus(),
            'amount'    => $amountCents,
        ];
    }
}
