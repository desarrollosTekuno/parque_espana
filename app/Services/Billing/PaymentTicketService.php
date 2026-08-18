<?php

namespace App\Services\Billing;

use App\Models\Billing\ClubPaymentMethod;
use App\Models\Administrator\Club;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PaymentTicketService {

    public function data(Payment $payment): array {
        return $this->tickets($payment)[0];
    }

    public function tickets(Payment $payment): array {
        $relations = [
            'club.clubAddress.city',
            'club.clubAddress.state',
            'club.clubAddress.country',
            'paymentMethod',
            'receiver',
            'membershipAccount.club.clubAddress.city',
            'membershipAccount.club.clubAddress.state',
            'membershipAccount.club.clubAddress.country',
            'membershipAccount.primaryHolder.member',
            'membershipAccount.fiscalData',
            'membershipAccount.currentLockerAssignments.locker',
            'membershipAccount.accountGroup.accounts.club.clubAddress.city',
            'membershipAccount.accountGroup.accounts.club.clubAddress.state',
            'membershipAccount.accountGroup.accounts.club.clubAddress.country',
            'membershipAccount.accountGroup.accounts.primaryHolder.member',
            'membershipAccount.accountGroup.accounts.fiscalData',
            'membershipAccount.accountGroup.accounts.memberships.club',
            'membershipAccount.accountGroup.accounts.currentLockerAssignments.locker',
            'applications.charge.concept',
            'applications.charge.membership.pricingRule',
            'applications.charge.membership.account',
        ];

        $payment->loadMissing($relations);

        $groupPayments = $payment->payment_group_id
            ? Payment::query()
                ->where('payment_group_id', $payment->payment_group_id)
                ->with($relations)
                ->orderBy('id')
                ->get()
            : collect([$payment]);

        $allocations = $this->ticketAllocations($groupPayments, $payment);
        $isMultiPark = $allocations->pluck('club_id')->unique()->count() > 1;

        return $allocations
            ->groupBy('club_id')
            ->map(function (Collection $clubAllocations, int|string $clubId) use ($groupPayments, $payment, $isMultiPark) {
                $representative = $clubAllocations->pluck('payment')->first() ?? $payment;
                $club = $this->clubForTicket((int) $clubId, $payment);
                $account = $this->accountForClub($payment->membershipAccount, (int) $clubId);
                $holder = $account?->primaryHolder?->member
                    ?? $payment->membershipAccount?->primaryHolder?->member;
                $ticketSeries = $this->cashierInitial($representative->receiver);
                $ticketFolio = $this->shortFolio($representative->folio);
                $subtotal = round((float) $clubAllocations->sum('subtotal'), 2);
                $ivaValue = round((float) $clubAllocations->sum('iva'), 2);

                return [
                    'payment_id' => $representative->id,
                    'payment_group_key' => $payment->payment_group_id ?: ('single-'.$payment->id),
                    'folio' => $representative->folio,
                    'ticket_serie' => $ticketSeries,
                    'ticket_folio' => $ticketFolio,
                    'identificacion_archivo' => $this->fileIdentifier(
                        $representative,
                        $account,
                        $ticketSeries,
                        $ticketFolio,
                        ! $isMultiPark,
                        (int) $clubId
                    ),
                    'fecha' => $representative->paid_at,
                    'estatus' => $representative->status,
                    'club_id' => (int) $clubId,
                    'club_codigo' => $club?->code,
                    'club_nombre' => $club?->name,
                    'club_nombre_institucion' => $this->institutionName($club?->code, $club?->name),
                    'club_razon_social' => $club?->legal_name,
                    'club_direccion_lineas' => $this->addressLines($club),
                    'club_rfc' => $club?->rfc,
                    'club_url_facturacion' => $club?->billing_url,
                    'club_logo_url' => $this->logoUrl($club?->code, $club?->logo_url),
                    'cajero_nombre' => $representative->receiver?->name,
                    'cajero_codigo' => $this->cashierCode($representative->receiver),
                    'cuenta_numero' => $account?->membership_number,
                    'cuenta_interna' => $account?->internal_account_number,
                    'titular' => $holder?->full_name,
                    'receptor_nombre' => $account?->fiscalData?->fiscal_name,
                    'receptor_rfc' => $account?->fiscalData?->rfc,
                    'receptor_uso_cfdi' => $account?->fiscalData?->cfdi_use,
                    'receptor_regimen_fiscal' => $account?->fiscalData?->fiscal_regime,
                    'receptor_codigo_postal' => $account?->fiscalData?->postal_code,
                    'casilleros' => $this->lockerCodes($account, (int) $clubId),
                    'conceptos' => $this->concepts($clubAllocations),
                    'forma_pago' => $representative->paymentMethod?->name,
                    'forma_pago_codigo' => $representative->paymentMethod?->code,
                    'forma_pago_ticket_codigo' => $this->paymentMethodTicketCode($representative->paymentMethod?->code),
                    'pago_identificacion' => $representative->check_number ?: $representative->reference,
                    'referencia' => $representative->reference,
                    'banco' => $representative->bank_name,
                    'numero_cheque' => $representative->check_number,
                    'es_pago_dividido' => $groupPayments->count() > 1,
                    // Las formas de pago de ESTE ticket son las que en
                    // verdad pertenecen a este parque (representedClubId,
                    // o el parque de la sesión si no se marcó ninguno) —
                    // NO se derivan del reparto 50/50 del concepto
                    // (ticketAllocations), porque ese reparto es informativo
                    // por mes/monto, pero el dinero de una tarjeta o
                    // efectivo específico sigue siendo 100% de un solo
                    // parque (p. ej. "Tarjeta de crédito (PE2)" solo debe
                    // aparecer en el ticket de PE2, con su monto completo).
                    'formas_de_pago' => $this->paymentMethods(
                        $groupPayments->filter(fn (Payment $item) => $this->resolvePaymentClubId($item) === (int) $clubId),
                        $representative
                    ),
                    'notas' => $representative->notes,
                    'leyenda_institucion' => 'DOS MESES SIN APORTACIÓN GENERAN SUSPENSIÓN',
                    'leyenda_no_fiscal' => 'Este comprobante no tiene validez fiscal.',
                    'subtotal' => $subtotal,
                    'iva' => $ivaValue > 0 ? $ivaValue : null,
                    'iva_porcentaje' => $ivaValue > 0 ? 16 : null,
                    'total' => round((float) $clubAllocations->sum('amount'), 2),
                ];
            })
            ->sortBy('club_id')
            ->values()
            ->all();
    }

    private function ticketAllocations(Collection $payments, Payment $requestedPayment): Collection {
        $actualMonthlyClubIds = $payments
            ->flatMap(fn (Payment $item) => $item->applications)
            ->filter(fn (PaymentApplication $application) => $application->charge?->concept?->code === 'MONTHLY_FEE')
            ->map(fn (PaymentApplication $application) => $application->charge?->membership?->club_id)
            ->filter()
            ->unique()
            ->values();

        $allocations = collect();

        foreach ($payments as $payment) {
            if ($payment->applications->isEmpty()) {
                $allocations->push([
                    'club_id' => $this->representedClubId($payment) ?: (int) $payment->club_id,
                    'payment_id' => $payment->id,
                    'payment' => $payment,
                    'application' => null,
                    'amount' => round((float) $payment->amount, 2),
                    'subtotal' => round((float) ($payment->subtotal ?? $payment->amount), 2),
                    'iva' => round((float) ($payment->iva ?? 0), 2),
                ]);
            } else {
                foreach ($payment->applications as $application) {
                    $clubIds = $this->applicationClubIds($application, $requestedPayment, $actualMonthlyClubIds);

                    // representedClubId (de dónde viene el dinero de ESTA
                    // forma de pago, ver PaymentLinePayload.club_id) solo
                    // decide el ticket de un cargo de UN solo parque — un
                    // cargo que de verdad se reparte entre dos parques
                    // (combo interclub, ver applicationClubIds) SIEMPRE se
                    // divide 50/50 por mes entre ambos tickets, sin
                    // importar qué forma de pago específica cubrió cada
                    // parte: de lo contrario, si el cajero no capturó los
                    // montos exactamente parejos entre las formas de pago
                    // de cada parque, un ticket terminaba con más meses (o
                    // más dinero por mes) que el otro, aunque la
                    // mensualidad combo en realidad es la misma para
                    // ambos parques cada mes.
                    if ($clubIds->count() === 1 && $this->representedClubId($payment)) {
                        $clubIds = collect([$this->representedClubId($payment)]);
                    }

                    $amounts = $this->splitStoredAmount((float) $application->applied_amount, $clubIds->count());
                    $subtotals = $this->splitStoredAmount(
                        (float) ($application->subtotal ?? $application->applied_amount),
                        $clubIds->count()
                    );
                    $ivas = $this->splitStoredAmount((float) ($application->iva ?? 0), $clubIds->count());
                    $discounts = $this->splitStoredAmount((float) ($application->discount ?? 0), $clubIds->count());

                    foreach ($clubIds as $index => $clubId) {
                        $allocations->push([
                            'club_id' => (int) $clubId,
                            'payment_id' => $payment->id,
                            'payment' => $payment,
                            'application' => $application,
                            'amount' => $amounts[$index],
                            'subtotal' => $subtotals[$index],
                            'iva' => $ivas[$index],
                            'discount' => $discounts[$index],
                        ]);
                    }
                }
            }
        }

        return $allocations;
    }

    private function applicationClubIds(PaymentApplication $application, Payment $payment, Collection $actualMonthlyClubIds): Collection {
        $charge = $application->charge;
        $chargeClubId = (int) ($charge?->membership?->club_id ?: $payment->club_id);

        if ($charge?->concept?->code !== 'MONTHLY_FEE' || $actualMonthlyClubIds->count() > 1) {
            return collect([$chargeClubId]);
        }

        $membership = $charge?->membership;
        $representsCombo = (bool) $membership?->interclub_package_rule_id
            || (bool) ($membership?->pricingRule?->requires_multiple_clubs ?? false);

        if (! $representsCombo) {
            return collect([$chargeClubId]);
        }

        $account = $payment->membershipAccount;
        $accounts = $account?->account_group_id && $account?->accountGroup
            ? $account->accountGroup->accounts
            : collect([$account]);

        $clubIds = $accounts
            ->flatMap(fn (MembershipAccount $item) => $item->memberships)
            ->filter(fn ($item) => $item->is_primary && in_array($item->status, ['active', 'suspended'], true))
            ->pluck('club_id')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $clubIds->count() > 1 ? $clubIds : collect([$chargeClubId]);
    }

    private function representedClubId(Payment $payment): ?int {
        $clubId = $payment->metadata['represents_club_id'] ?? null;

        return $clubId ? (int) $clubId : null;
    }

    private function splitStoredAmount(float $amount, int $parts): array {
        if ($parts <= 1) {
            return [round($amount, 2)];
        }

        $result = [];
        $remaining = round($amount, 2);

        for ($index = 0; $index < $parts; $index++) {
            $part = $index === $parts - 1 ? $remaining : round($amount / $parts, 2);
            $result[] = $part;
            $remaining = round($remaining - $part, 2);
        }

        return $result;
    }

    private function concepts(Collection $allocations): array {
        return $allocations
            ->filter(fn (array $allocation) => $allocation['application'] !== null)
            ->map(function (array $allocation) {
                $charge = $allocation['application']->charge;

                // Cantidad capturada en el panel de Cobranza (p. ej. "3 x
                // $300.00" de un pase diario, ver
                // CollectionController::storePayment) — sin esto, un cargo
                // con cantidad > 1 se ve en el ticket como "1 x $900.00".
                $capturedQuantity = (int) ($charge?->metadata['quantity'] ?? 1);

                return [
                    'charge_id' => $charge?->id,
                    'codigo' => $charge?->concept?->internal_key ?: $charge?->concept?->code,
                    'concepto' => $charge?->concept?->name,
                    'descripcion' => $charge?->description,
                    'cantidad' => $capturedQuantity > 1 ? $capturedQuantity : 1,
                    'importe_unitario' => $allocation['subtotal'],
                    'total' => $allocation['subtotal'],
                    'descuento' => round((float) ($allocation['discount'] ?? 0), 2),
                    'monto' => $allocation['amount'],
                    'period_year' => $charge?->period_year,
                    'period_month' => $charge?->period_month,
                ];
            })
            ->groupBy(function (array $item) {
                $hasPeriod = $item['period_year'] && $item['period_month'];
                $key = $item['codigo'] ?? $item['charge_id'];

                // Los cargos con periodo (mes/año, p. ej. mensualidad) se
                // agrupan por concepto sin importar el importe de cada uno
                // — el monto por mes puede variar (ajuste de tarifa a
                // mitad de año, reparto distinto entre parques en un
                // combo, etc.) y aun así deben verse como un solo renglón
                // "Septiembre a Noviembre 2026", no partidos en varios
                // porque el precio no coincidió exacto. Sin periodo, se
                // agrupa por charge_id: un mismo cargo (p. ej. "3 pases
                // diarios a $300") siempre debe verse como UN renglón,
                // aunque su pago se haya dividido entre varias formas de
                // pago con montos distintos (antes se agrupaba por importe
                // exacto de cada renglón de pago, y ese reparto desigual lo
                // partía en dos líneas con precio unitario distinto).
                return $hasPeriod ? "{$key}|period" : "{$key}|{$item['charge_id']}";
            })
            ->map(function (Collection $items) {
                $concept = $items->first();

                // Un mismo mes puede quedar cubierto por más de un renglón
                // de payment_applications (una mensualidad dividida en dos
                // formas de pago, o el descuento adjunto aparte del dinero
                // real, ver PaymentRegistrationService::registerSplit) —
                // "cantidad" debe contar MESES distintos, no renglones, o
                // un diciembre pagado con dos tarjetas se vería como "2
                // meses" en vez de uno.
                $uniquePeriods = $items
                    ->filter(fn (array $item) => $item['period_year'] && $item['period_month'])
                    ->map(fn (array $item) => "{$item['period_year']}-{$item['period_month']}")
                    ->unique();

                // Sin periodo: la cantidad capturada (ver 'cantidad' arriba)
                // es la misma para todos los renglones de UN mismo cargo
                // (aunque ese cargo se haya dividido en más de una forma de
                // pago) — se suma por charge_id único para no duplicarla, y
                // así poder juntar además dos compras distintas del mismo
                // concepto al mismo precio (p. ej. dos altas de "3 pases
                // diarios a $300") en un solo renglón de "6".
                $cantidad = $uniquePeriods->isNotEmpty()
                    ? $uniquePeriods->count()
                    : $items->unique('charge_id')->sum('cantidad');

                $concept['cantidad'] = $cantidad;
                $concept['total'] = round((float) $items->sum('total'), 2);
                $concept['monto'] = round((float) $items->sum('monto'), 2);
                $descuento = round((float) $items->sum('descuento'), 2);
                $concept['descuento'] = $descuento > 0 ? $descuento : null;

                if ($cantidad > 1) {
                    $rangeLabel = $this->periodRangeLabel($items);

                    if ($rangeLabel !== null) {
                        $concept['descripcion'] = $rangeLabel;
                    } elseif ($items->count() > 1) {
                        // Se juntaron varios cargos distintos (p. ej. dos
                        // altas del mismo concepto al mismo precio) en un
                        // solo renglón — usa el nombre del concepto en vez
                        // de la descripción de nada más el primero. Si es
                        // UN solo cargo con cantidad > 1 (ver 'cantidad'
                        // arriba), se deja la descripción tal cual la
                        // capturó el cajero.
                        $concept['descripcion'] = $concept['concepto'];
                    }

                    // El importe pudo variar (precio distinto entre
                    // renglones, o solo repartir el total entre la
                    // cantidad capturada) — se muestra el promedio para que
                    // Cantidad × Importe U. siga cuadrando con el Total
                    // (mismo criterio que ya usa Cobranza para la "Cuota"
                    // en su tabla de cargos pendientes).
                    $concept['importe_unitario'] = round($concept['total'] / $cantidad, 2);
                }

                unset($concept['period_year'], $concept['period_month']);

                return $concept;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{period_year: ?int, period_month: ?int}>  $items
     */
    private function periodRangeLabel(Collection $items): ?string {
        $periods = $items
            ->filter(fn (array $item) => $item['period_year'] && $item['period_month'])
            ->map(fn (array $item) => Carbon::create((int) $item['period_year'], (int) $item['period_month'], 1));

        if ($periods->count() !== $items->count()) {
            // Algún cargo del grupo no trae periodo (p. ej. no es
            // mensualidad) — no se puede armar un rango confiable, se cae
            // al nombre genérico del concepto.
            return null;
        }

        $sorted = $periods->sort()->values();
        $first = $sorted->first();
        $last = $sorted->last();

        if ($first->isSameMonth($last)) {
            return ucfirst($first->locale('es')->translatedFormat('F Y'));
        }

        $sameYear = $first->year === $last->year;
        $firstLabel = ucfirst($first->locale('es')->translatedFormat($sameYear ? 'F' : 'F Y'));
        $lastLabel = ucfirst($last->locale('es')->translatedFormat('F Y'));

        return "{$firstLabel} a {$lastLabel}";
    }

    private function paymentMethods(Collection $payments, Payment $representative): array {
        return $payments
            ->map(fn (Payment $payment) => [
                'payment_id' => $payment->id,
                'folio' => $payment->folio,
                'ticket_serie' => $this->cashierInitial($payment->receiver),
                'ticket_folio' => $this->shortFolio($payment->folio),
                'nombre' => $payment->paymentMethod?->name,
                'codigo' => $this->resolveInternalKey($payment),
                'codigo_ticket' => $this->paymentMethodTicketCode($payment->paymentMethod?->code),
                'monto' => round((float) $payment->amount, 2),
                'referencia' => $payment->reference,
                'banco' => $payment->bank_name,
                'numero_cheque' => $payment->check_number,
                'es_este_ticket' => $payment->id === $representative->id,
                'status' => $payment->status,
            ])
            ->values()
            ->all();
    }

    /** A qué parque pertenece el DINERO de esta forma de pago — el que
     *  marcó explícitamente el cajero (represents_club_id) o, si no marcó
     *  ninguno, el parque de la sesión donde se registró. */
    private function resolvePaymentClubId(Payment $payment): int {
        return $this->representedClubId($payment) ?? (int) $payment->club_id;
    }

    private function clubForTicket(int $clubId, Payment $payment): ?Club {
        if ((int) $payment->club_id === $clubId) {
            return $payment->club;
        }

        $account = $this->accountForClub($payment->membershipAccount, $clubId);

        return $account?->club
            ?? Club::query()->with(['clubAddress.city', 'clubAddress.state', 'clubAddress.country'])->find($clubId);
    }

    private function accountForClub(?MembershipAccount $account, int $clubId): ?MembershipAccount {
        if (! $account) {
            return null;
        }

        if ((int) $account->club_id === $clubId) {
            return $account;
        }

        return $account->account_group_id && $account->accountGroup
            ? $account->accountGroup->accounts->first(fn (MembershipAccount $item) => (int) $item->club_id === $clubId)
            : $account;
    }

    private function addressLines(?Club $club): array {
        $address = $club?->clubAddress;

        if (! $address) {
            return [];
        }

        $lines = [];
        $street = trim((string) $address->street);

        if ($address->exterior_number) {
            $street .= ' #'.$address->exterior_number;
        }

        if ($address->interior_number) {
            $street .= ' Int. '.$address->interior_number;
        }

        if ($street !== '') {
            $lines[] = $street;
        }

        $neighborhoodPostalCode = $address->neighborhood ? 'Col. '.$address->neighborhood : '';

        if ($address->postal_code) {
            $neighborhoodPostalCode .= ($neighborhoodPostalCode !== '' ? ' ' : '').'CP '.$address->postal_code;
        }

        if ($neighborhoodPostalCode !== '') {
            $lines[] = $neighborhoodPostalCode;
        }

        $cityStateCountry = collect([
            $address->city?->name,
            $address->state?->name,
            $address->country?->name,
        ])->filter()->implode(' ');

        if ($cityStateCountry !== '') {
            $lines[] = $cityStateCountry;
        }

        return $lines;
    }

    private function logoUrl(?string $clubCode, ?string $configuredLogo): ?string {
        return match (strtoupper((string) $clubCode)) {
            'PE1' => '/assets/images/LogoP1.png',
            'PE2' => '/assets/images/LogoP2.png',
            default => $configuredLogo,
        };
    }

    private function institutionName(?string $clubCode, ?string $fallback): string {
        return match (strtoupper((string) $clubCode)) {
            'PE1' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA I',
            'PE2' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA II',
            default => $fallback ?? 'Parque',
        };
    }

    private function cashierInitial(?User $cashier): ?string {
        $name = trim((string) $cashier?->name);

        return $name === '' ? null : strtoupper(Str::ascii(mb_substr($name, 0, 1)));
    }

    private function cashierCode(?User $cashier): ?string {
        $code = trim((string) $cashier?->code);

        if ($code !== '') {
            return strtoupper($code);
        }

        $words = preg_split('/\s+/', trim(Str::ascii((string) $cashier?->name))) ?: [];
        $initials = '';

        foreach ($words as $word) {
            if ($word !== '') {
                $initials .= strtoupper(substr($word, 0, 1));
            }
        }

        return $initials !== '' ? $initials : null;
    }

    private function lockerCodes(?MembershipAccount $account, int $clubId): array {
        if (! $account) {
            return [];
        }

        return $account->currentLockerAssignments
            ->filter(fn ($assignment) => (int) $assignment->club_id === $clubId)
            ->unique('id')
            ->sortBy('locker_id')
            ->map(function ($assignment) {
                $category = strtoupper(substr(Str::ascii((string) $assignment->locker?->category), 0, 2));
                $number = str_pad((string) $assignment->locker?->number, 5, '0', STR_PAD_LEFT);

                return $category.$number;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveInternalKey(Payment $p): ?string {
        if (!$p->payment_method_id) {
            return $p->paymentMethod?->code;
        }

        $clubId = $this->representedClubId($p) ?: $p->club_id;

        return ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->where('payment_method_id', $p->payment_method_id)
            ->value('internal_key') ?: $p->paymentMethod?->code;
    }

    private function paymentMethodTicketCode(?string $code): ?string {
        return match ($code) {
            'CASH' => 'EF',
            'BANK_TRANSFER' => 'TR',
            'APP_PAYMENT' => 'AP',
            'CHECK' => 'CH',
            'CREDIT_CARD' => 'TC',
            'DEBIT_CARD' => 'TD',
            default => $code,
        };
    }

    private function shortFolio(?string $folio): ?string {
        if (! $folio) {
            return null;
        }

        $parts = explode('-', $folio);
        $date = $parts[count($parts) - 2] ?? null;
        $consecutive = $parts[count($parts) - 1] ?? null;

        if ($date && $consecutive && preg_match('/^\d{6}$/', $date) && preg_match('/^\d+$/', $consecutive)) {
            return substr($date, -2).str_pad($consecutive, 3, '0', STR_PAD_LEFT);
        }

        return $folio;
    }

    private function fileIdentifier(
        Payment $payment,
        ?MembershipAccount $account,
        ?string $ticketSeries,
        ?string $ticketFolio,
        bool $persist,
        int $clubId
    ): ?string {
        if ($persist && $payment->ticket_file_identifier) {
            return $payment->ticket_file_identifier;
        }

        if (! $account || ! $ticketSeries || ! $ticketFolio) {
            return null;
        }

        $accountNumber = $account->membership_number ?: $account->internal_account_number;
        $accountDigits = preg_replace('/\D/', '', (string) $accountNumber);

        if ($accountDigits === '') {
            return null;
        }

        $accountPart = str_pad($accountDigits, 10, '0', STR_PAD_LEFT);
        $suffix = $persist
            ? strtoupper(Str::random(9))
            : strtoupper(substr(hash('sha256', ($payment->payment_group_id ?: $payment->id).'|'.$clubId), 0, 9));
        $identifier = $accountPart.'DP'.$ticketSeries.$ticketSeries.$ticketFolio.$suffix;

        if ($persist) {
            $payment->ticket_file_identifier = $identifier;

            if ($payment->exists) {
                $payment->saveQuietly();
            }
        }

        return $identifier;
    }
}
