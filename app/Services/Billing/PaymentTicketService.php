<?php

namespace App\Services\Billing;

use App\Models\Billing\Payment;
use App\Models\User;
use Illuminate\Support\Str;

class PaymentTicketService
{
    public function data(Payment $payment): array
    {
        $payment->loadMissing([
            'club.clubAddress.city',
            'club.clubAddress.state',
            'club.clubAddress.country',
            'paymentMethod',
            'receiver',
            'membershipAccount.primaryHolder.member',
            'applications.charge.concept',
        ]);

        $club = $payment->club;
        $account = $payment->membershipAccount;
        $holder = $account?->primaryHolder?->member;
        $addressLines = $this->addressLines($payment);
        $subtotal = null;
        $iva = null;

        if ($club?->applies_iva) {
            $subtotal = round((float) $payment->amount / 1.16, 2);
            $iva = round((float) $payment->amount - $subtotal, 2);
        }

        return [
            'payment_id' => $payment->id,
            'folio' => $payment->folio,
            'ticket_serie' => $this->cashierInitial($payment->receiver),
            'ticket_folio' => $this->shortFolio($payment->folio),
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
            'cuenta_numero' => $account?->membership_number,
            'cuenta_interna' => $account?->internal_account_number,
            'titular' => $holder?->full_name,
            'conceptos' => $payment->applications->map(function ($application) {
                $charge = $application->charge;

                return [
                    'charge_id' => $charge?->id,
                    'codigo' => $charge?->concept?->code,
                    'concepto' => $charge?->concept?->name,
                    'descripcion' => $charge?->description,
                    'monto' => (float) $application->applied_amount,
                ];
            })->values()->all(),
            'forma_pago' => $payment->paymentMethod?->name,
            'forma_pago_codigo' => $payment->paymentMethod?->code,
            'referencia' => $payment->reference,
            'banco' => $payment->bank_name,
            'numero_cheque' => $payment->check_number,
            'notas' => $payment->notes,
            'subtotal' => $subtotal,
            'iva' => $iva,
            'iva_porcentaje' => $club?->applies_iva ? 16 : null,
            'total' => (float) $payment->amount,
            'desglose_parques' => $payment->metadata['park_split'] ?? null,
        ];
    }

    private function addressLines(Payment $payment): array
    {
        $club = $payment->club;
        $address = $club?->clubAddress;

        if (!$address) {
            return $club?->address ? [$club->address] : [];
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

        if ($name === '') {
            return null;
        }

        return strtoupper(Str::ascii(mb_substr($name, 0, 1)));
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

    private function shortFolio(?string $folio): ?string
    {
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
}
