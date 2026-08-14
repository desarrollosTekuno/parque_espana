<?php

namespace App\Services\Billing;

use App\Models\Billing\ClubPaymentMethod;
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

        // Si este pago es parte de un cobro dividido en varias formas de
        // pago (ver PaymentRegistrationService::registerSplit), el ticket
        // muestra la información COMPLETA del cobro — todas las formas de
        // pago, todos los conceptos, el total conjunto — no solo lo que
        // cubrió este pago en particular, aunque cada forma de pago sigue
        // teniendo su propio folio/ticket físico. Sin payment_group_id
        // (pagos de una sola forma, o de antes de este campo) el "grupo" es
        // nada más este pago.
        $groupPayments = $payment->payment_group_id
            ? Payment::query()
                ->where('payment_group_id', $payment->payment_group_id)
                ->with(['paymentMethod', 'applications.charge.concept'])
                ->orderBy('id')
                ->get()
            : collect([$payment]);

        $club = $payment->club;
        $account = $payment->membershipAccount;
        $holder = $account?->primaryHolder?->member;
        $addressLines = $this->addressLines($payment);
        $total = round((float) $groupPayments->sum('amount'), 2);
        $ticketSeries = $this->cashierInitial($payment->receiver);
        $ticketFolio = $this->shortFolio($payment->folio);

        $groupApplications = $groupPayments->flatMap(fn (Payment $p) => $p->applications);

        // El subtotal/IVA real se guarda por línea (PaymentApplication, ver
        // PaymentRegistrationService::resolveApplicationSubtotalAndIva) desde
        // que un mismo pago puede cubrir cargos de conceptos distintos —
        // unos con IVA, otros sin — así que no se puede calcular con un
        // porcentaje parejo sobre el total del pago (eso daba resultados
        // incorrectos en cobros mixtos). Aplicaciones de antes de que
        // existiera este campo (subtotal/iva null) se asumen sin IVA.
        $subtotal = round((float) $groupApplications->sum(
            fn ($application) => $application->subtotal ?? (float) $application->applied_amount
        ), 2);
        $iva = round((float) $groupApplications->sum(fn ($application) => $application->iva ?? 0.0), 2);
        $iva = $iva > 0 ? $iva : null;

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
            'conceptos' => $groupApplications->map(function ($application) {
                $charge = $application->charge;
                $amount = (float) $application->applied_amount;
                $unitPrice = $application->subtotal ?? $amount;

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
            // Se conservan por compatibilidad (algún ticket viejo podría
            // solo usar estos) — reflejan nada más la forma de pago DE ESTE
            // registro. Para el desglose completo del cobro, ver
            // formas_de_pago / es_pago_dividido.
            'forma_pago' => $payment->paymentMethod?->name,
            'forma_pago_codigo' => $payment->paymentMethod?->code,
            'forma_pago_ticket_codigo' => $this->paymentMethodTicketCode($payment->paymentMethod?->code),
            'pago_identificacion' => $payment->check_number ?: $payment->reference,
            'referencia' => $payment->reference,
            'banco' => $payment->bank_name,
            'numero_cheque' => $payment->check_number,
            'es_pago_dividido' => $groupPayments->count() > 1,
            'formas_de_pago' => $groupPayments->map(function (Payment $p) use ($payment) {
                return [
                    'payment_id' => $p->id,
                    'nombre' => $p->paymentMethod?->name,
                    'codigo' => $this->resolveInternalKey($p),
                    'codigo_ticket' => $this->paymentMethodTicketCode($p->paymentMethod?->code),
                    'monto' => (float) $p->amount,
                    'referencia' => $p->reference,
                    'banco' => $p->bank_name,
                    'numero_cheque' => $p->check_number,
                    'es_este_ticket' => $p->id === $payment->id,
                    'status' => $p->status,
                ];
            })->values()->all(),
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

    /**
     * La "clave interna" real de un método de pago es POR PARQUE (ver
     * billing.club_payment_methods.internal_key, p. ej. CHP1/CHP2 para
     * cheque en parque 1/2) — no el code genérico del catálogo
     * (billing.payment_methods.code, p. ej. CHECK). Se resuelve contra el
     * parque que esta línea representa (metadata.represents_club_id en
     * pagos cruzados de un cobro dividido, o el club_id del pago si no
     * aplica) — igual que el resto de la atribución por parque de esta
     * clase. Si no hay configuración para esa combinación, se cae al code
     * genérico para no dejar la clave vacía.
     */
    private function resolveInternalKey(Payment $p): ?string {
        if (!$p->payment_method_id) {
            return $p->paymentMethod?->code;
        }

        $clubId = $p->metadata['represents_club_id'] ?? $p->club_id;

        $internalKey = ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->where('payment_method_id', $p->payment_method_id)
            ->value('internal_key');

        return $internalKey ?: $p->paymentMethod?->code;
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
