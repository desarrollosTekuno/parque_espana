<?php

namespace App\Services\Billing;

use App\Models\Administrator\Club;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PaymentTicketService
{
    public function data(Payment $payment): array
    {
        return $this->tickets($payment)[0];
    }

    public function tickets(Payment $payment): array
    {
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
                $representative = $groupPayments->first() ?? $payment;
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
                    'formas_de_pago' => $this->paymentMethods($clubAllocations, $representative),
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

    private function ticketAllocations(Collection $payments, Payment $requestedPayment): Collection
    {
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
                    'club_id' => (int) $payment->club_id,
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
                    $amounts = $this->splitStoredAmount((float) $application->applied_amount, $clubIds->count());
                    $subtotals = $this->splitStoredAmount(
                        (float) ($application->subtotal ?? $application->applied_amount),
                        $clubIds->count()
                    );
                    $ivas = $this->splitStoredAmount((float) ($application->iva ?? 0), $clubIds->count());

                    foreach ($clubIds as $index => $clubId) {
                        $allocations->push([
                            'club_id' => (int) $clubId,
                            'payment_id' => $payment->id,
                            'payment' => $payment,
                            'application' => $application,
                            'amount' => $amounts[$index],
                            'subtotal' => $subtotals[$index],
                            'iva' => $ivas[$index],
                        ]);
                    }
                }
            }
        }

        return $allocations;
    }

    private function applicationClubIds(
        PaymentApplication $application,
        Payment $payment,
        Collection $actualMonthlyClubIds
    ): Collection {
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

    private function splitStoredAmount(float $amount, int $parts): array
    {
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

    private function concepts(Collection $allocations): array
    {
        return $allocations
            ->filter(fn (array $allocation) => $allocation['application'] !== null)
            ->map(function (array $allocation) {
                $charge = $allocation['application']->charge;

                return [
                    'charge_id' => $charge?->id,
                    'codigo' => $charge?->concept?->code,
                    'concepto' => $charge?->concept?->name,
                    'descripcion' => $charge?->description,
                    'cantidad' => 1,
                    'importe_unitario' => $allocation['subtotal'],
                    'total' => $allocation['subtotal'],
                    'descuento' => null,
                    'monto' => $allocation['amount'],
                ];
            })
            ->groupBy(fn (array $item) => ($item['codigo'] ?? $item['charge_id']).'|'.number_format($item['importe_unitario'], 2, '.', ''))
            ->map(function (Collection $items) {
                $concept = $items->first();
                $concept['cantidad'] = $items->count();
                $concept['total'] = round((float) $items->sum('total'), 2);
                $concept['monto'] = round((float) $items->sum('monto'), 2);

                if ($items->count() > 1) {
                    $concept['descripcion'] = $concept['concepto'];
                }

                return $concept;
            })
            ->values()
            ->all();
    }

    private function paymentMethods(Collection $allocations, Payment $representative): array
    {
        return $allocations
            ->groupBy('payment_id')
            ->map(function (Collection $paymentAllocations) use ($representative) {
                /** @var Payment $payment */
                $payment = $paymentAllocations->first()['payment'];

                return [
                    'payment_id' => $payment->id,
                    'folio' => $payment->folio,
                    'ticket_serie' => $this->cashierInitial($payment->receiver),
                    'ticket_folio' => $this->shortFolio($payment->folio),
                    'nombre' => $payment->paymentMethod?->name,
                    'codigo' => $this->resolveInternalKey($payment),
                    'codigo_ticket' => $this->paymentMethodTicketCode($payment->paymentMethod?->code),
                    'monto' => round((float) $paymentAllocations->sum('amount'), 2),
                    'referencia' => $payment->reference,
                    'banco' => $payment->bank_name,
                    'numero_cheque' => $payment->check_number,
                    'es_este_ticket' => $payment->id === $representative->id,
                    'status' => $payment->status,
                ];
            })
            ->values()
            ->all();
    }

    private function clubForTicket(int $clubId, Payment $payment): ?Club
    {
        if ((int) $payment->club_id === $clubId) {
            return $payment->club;
        }

        $account = $this->accountForClub($payment->membershipAccount, $clubId);

        return $account?->club
            ?? Club::query()->with(['clubAddress.city', 'clubAddress.state', 'clubAddress.country'])->find($clubId);
    }

    private function accountForClub(?MembershipAccount $account, int $clubId): ?MembershipAccount
    {
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

    private function addressLines(?Club $club): array
    {
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

    private function logoUrl(?string $clubCode, ?string $configuredLogo): ?string
    {
        return match (strtoupper((string) $clubCode)) {
            'PE1' => '/assets/images/LogoP1.png',
            'PE2' => '/assets/images/LogoP2.png',
            default => $configuredLogo,
        };
    }

    private function institutionName(?string $clubCode, ?string $fallback): string
    {
        return match (strtoupper((string) $clubCode)) {
            'PE1' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA I',
            'PE2' => 'FUNDACIÓN DEPORTIVO PARQUE ESPAÑA II',
            default => $fallback ?? 'Parque',
        };
    }

    private function cashierInitial(?User $cashier): ?string
    {
        $name = trim((string) $cashier?->name);

        return $name === '' ? null : strtoupper(Str::ascii(mb_substr($name, 0, 1)));
    }

    private function cashierCode(?User $cashier): ?string
    {
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

    private function lockerCodes(?MembershipAccount $account, int $clubId): array
    {
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

    private function resolveInternalKey(Payment $payment): ?string
    {
        if (! $payment->payment_method_id) {
            return $payment->paymentMethod?->code;
        }

        $clubId = $payment->metadata['represents_club_id'] ?? $payment->club_id;

        return ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->where('payment_method_id', $payment->payment_method_id)
            ->value('internal_key') ?: $payment->paymentMethod?->code;
    }

    private function paymentMethodTicketCode(?string $code): ?string
    {
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

    private function shortFolio(?string $folio): ?string
    {
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
