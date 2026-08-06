<?php

namespace App\Services\Billing;

use App\Models\Billing\Payment;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Illuminate\Support\Str;

class PaymentTicketService {

    public function data(Payment $payment): array {
        $payment->loadMissing([
            'club.clubAddress.city',
            'club.clubAddress.state',
            'club.clubAddress.country',
            'paymentMethod',
            'receiver',
            'membershipAccount.primaryHolder.member',
            'membershipAccount.accountGroup.accounts',
            'membershipAccount.accountGroup.accounts.currentLockerAssignments.locker',
            'membershipAccount.currentLockerAssignments.locker',
            'membershipAccount.fiscalData',
            'applications.charge.concept',
        ]);

        $club = $payment->club;
        $account = $payment->membershipAccount;
        $holder = $account?->primaryHolder?->member;
        $addressLines = $this->addressLines($payment);
        $total = round((float) $payment->amount, 2);
        $subtotal = $total;
        $iva = null;
        $ticketSeries = $this->cashierInitial($payment->receiver);
        $ticketFolio = $this->shortFolio($payment->folio);

        if ($club?->applies_iva) {
            $subtotal = round($total / 1.16, 2);
            $iva = round($total - $subtotal, 2);
        }

        return [
            'payment_id' => $payment->id,
            'folio' => $payment->folio,
            'ticket_serie' => $ticketSeries,
            'ticket_folio' => $ticketFolio,
            'identificacion_archivo' => $this->fileIdentifier($payment, $account, $ticketSeries, $ticketFolio),
            'fecha' => $payment->paid_at,
            'estatus' => $payment->status,
            'club_id' => $payment->club_id,
            'club_codigo' => $club?->code,
            'club_nombre' => $club?->name,
            'club_nombre_institucion' => $this->institutionName($club?->code, $club?->name),
            'club_razon_social' => $club?->legal_name,
            'club_direccion_lineas' => $addressLines,
            'club_rfc' => $club?->rfc,
            'club_url_facturacion' => $club?->billing_url,
            'club_logo_url' => $this->logoUrl($club?->code, $club?->logo_url),
            'cajero_nombre' => $payment->receiver?->name,
            'cajero_codigo' => $this->cashierCode($payment->receiver),
            'cuenta_numero' => $this->accountNumbers($account),
            'cuenta_interna' => $account?->internal_account_number,
            'titular' => $holder?->full_name,
            'receptor_nombre' => $account?->fiscalData?->fiscal_name,
            'receptor_rfc' => $account?->fiscalData?->rfc,
            'receptor_uso_cfdi' => $account?->fiscalData?->cfdi_use,
            'receptor_regimen_fiscal' => $account?->fiscalData?->fiscal_regime,
            'receptor_codigo_postal' => $account?->fiscalData?->postal_code,
            'casilleros' => $this->lockerCodes($account),
            'conceptos' => $payment->applications->map(function ($application) use ($club) {
                $charge = $application->charge;
                $amount = (float) $application->applied_amount;
                $unitPrice = $club?->applies_iva ? round($amount / 1.16, 2) : $amount;

                return [
                    'charge_id' => $charge?->id,
                    'codigo' => $charge?->concept?->code,
                    'concepto' => $charge?->concept?->name,
                    'descripcion' => $charge?->description,
                    'cantidad' => 1,
                    'importe_unitario' => $unitPrice,
                    'total' => $unitPrice,
                    'descuento' => null,
                    'monto' => $amount,
                ];
            })->groupBy(function (array $item) {
                return ($item['codigo'] ?? $item['charge_id']) . '|' . number_format($item['importe_unitario'], 2, '.', '');
            })->map(function ($items) {
                $concept = $items->first();
                $concept['cantidad'] = $items->count();
                $concept['total'] = round($items->sum('total'), 2);
                $concept['monto'] = round($items->sum('monto'), 2);

                if ($items->count() > 1) {
                    $concept['descripcion'] = $concept['concepto'];
                }

                return $concept;
            })->values()->all(),
            'forma_pago' => $payment->paymentMethod?->name,
            'forma_pago_codigo' => $payment->paymentMethod?->code,
            'forma_pago_ticket_codigo' => $this->paymentMethodTicketCode($payment->paymentMethod?->code),
            'pago_identificacion' => $payment->check_number ?: $payment->reference,
            'referencia' => $payment->reference,
            'banco' => $payment->bank_name,
            'numero_cheque' => $payment->check_number,
            'notas' => $payment->notes,
            'leyenda_institucion' => 'DOS MESES SIN APORTACIÓN GENERAN SUSPENSIÓN',
            'leyenda_no_fiscal' => 'Este comprobante no tiene validez fiscal.',
            'subtotal' => $subtotal,
            'iva' => $iva,
            'iva_porcentaje' => $club?->applies_iva ? 16 : null,
            'total' => $total,
            'desglose_parques' => $payment->metadata['park_split'] ?? null,
        ];
    }

    private function addressLines(Payment $payment): array {
        $club = $payment->club;
        $address = $club?->clubAddress;

        if (!$address) {
            return [];
        }

        $lines = [];
        $street = trim((string) $address->street);

        if ($address->exterior_number) {
            $street .= ' #' . $address->exterior_number;
        }

        if ($address->interior_number) {
            $street .= ' Int. ' . $address->interior_number;
        }

        if ($street !== '') {
            $lines[] = $street;
        }

        $neighborhoodPostalCode = '';

        if ($address->neighborhood) {
            $neighborhoodPostalCode = 'Col. ' . $address->neighborhood;
        }

        if ($address->postal_code) {
            $neighborhoodPostalCode .= ($neighborhoodPostalCode !== '' ? ' ' : '')
                . 'CP ' . $address->postal_code;
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

        if ($name === '') {
            return null;
        }

        return strtoupper(Str::ascii(mb_substr($name, 0, 1)));
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

    private function accountNumbers(?MembershipAccount $account): ?string {
        if (!$account) {
            return null;
        }

        $accounts = collect([$account]);

        if ($account->account_group_id && $account->accountGroup) {
            $accounts = $account->accountGroup->accounts;
        }

        $numbers = $accounts
            ->sortBy('club_id')
            ->map(fn (MembershipAccount $item) => $item->membership_number ?: $item->internal_account_number)
            ->filter()
            ->unique()
            ->implode(' / ');

        return $numbers !== '' ? $numbers : null;
    }

    private function lockerCodes(?MembershipAccount $account): array {
        if (!$account) {
            return [];
        }

        $accounts = collect([$account]);

        if ($account->account_group_id && $account->accountGroup) {
            $accounts = $account->accountGroup->accounts;
        }

        return $accounts
            ->flatMap(fn (MembershipAccount $item) => $item->currentLockerAssignments)
            ->unique('id')
            ->sortBy([
                ['club_id', 'asc'],
                ['locker_id', 'asc'],
            ])
            ->map(function ($assignment) {
                $category = strtoupper(substr(Str::ascii((string) $assignment->locker?->category), 0, 2));
                $number = str_pad((string) $assignment->locker?->number, 5, '0', STR_PAD_LEFT);

                return $category . $number;
            })
            ->filter()
            ->values()
            ->all();
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
        if (!$folio) {
            return null;
        }

        $parts = explode('-', $folio);
        $date = $parts[count($parts) - 2] ?? null;
        $consecutive = $parts[count($parts) - 1] ?? null;

        if ($date && $consecutive && preg_match('/^\d{6}$/', $date) && preg_match('/^\d+$/', $consecutive)) {
            return substr($date, -2) . str_pad($consecutive, 3, '0', STR_PAD_LEFT);
        }

        return $folio;
    }

    private function fileIdentifier(
        Payment $payment,
        ?MembershipAccount $account,
        ?string $ticketSeries,
        ?string $ticketFolio
    ): ?string {
        if ($payment->ticket_file_identifier) {
            return $payment->ticket_file_identifier;
        }

        if (!$account || !$ticketSeries || !$ticketFolio) {
            return null;
        }

        $accountNumber = $account->membership_number ?: $account->internal_account_number;
        $accountDigits = preg_replace('/\D/', '', (string) $accountNumber);

        if ($accountDigits === '') {
            return null;
        }

        $accountPart = str_pad($accountDigits, 10, '0', STR_PAD_LEFT);
        $identifier = $accountPart
            . 'DP'
            . $ticketSeries
            . $ticketSeries
            . $ticketFolio
            . strtoupper(Str::random(9));

        $payment->ticket_file_identifier = $identifier;

        if ($payment->exists) {
            $payment->saveQuietly();
        }

        return $identifier;
    }
}
