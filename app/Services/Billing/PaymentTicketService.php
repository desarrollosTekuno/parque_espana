<?php

namespace App\Services\Billing;

use App\Models\Billing\Payment;

class PaymentTicketService {

    public function data(Payment $payment) {
        $payment->load([
            'club.clubAddress.city',
            'club.clubAddress.state',
            'paymentMethod',
            'receiver',
            'membershipAccount.primaryHolder.member',
            'applications.charge',
        ]);

        $folioCorto = $this->calcularFolioCorto($payment->folio);

        $conceptos = [];

        foreach ($payment->applications as $aplicacion) {
            $charge = $aplicacion->charge;

            $conceptos[] = [
                'descripcion' => $charge ? $charge->description : null,
                'monto' => $aplicacion->applied_amount,
            ];
        }

        $titular = null;

        if ($payment->membershipAccount) {
            if ($payment->membershipAccount->primaryHolder) {
                if ($payment->membershipAccount->primaryHolder->member) {
                    $titular = $payment->membershipAccount->primaryHolder->member->full_name;
                }
            }
        }

        $clubNombre = null;
        $clubRazonSocialLineas = [];
        $clubDireccionLineas = [];
        $clubRfc = null;
        $clubUrlFacturacion = null;
        $clubLogoUrl = null;
        $aplicaIva = false;

        if ($payment->club) {
            $clubNombre = $payment->club->name;
            $clubRazonSocialLineas = $this->razonSocialLineas($payment->club->code);
            $clubRfc = $payment->club->rfc;
            $clubUrlFacturacion = $payment->club->billing_url;
            $clubLogoUrl = $this->logoUrlPorClub($payment->club->code);
            $aplicaIva = $payment->club->applies_iva;
            $clubDireccionLineas = $this->armarLineasDireccion($payment->club->clubAddress);
        }

        $subtotal = null;
        $iva = null;

        if ($aplicaIva) {
            $subtotal = round($payment->amount / 1.16, 2);
            $iva = round($payment->amount - $subtotal, 2);
        }

        $cajeroNombre = null;
        $cajeroCodigo = null;

        if ($payment->receiver) {
            $cajeroNombre = $payment->receiver->name;
            $cajeroCodigo = $payment->receiver->code;
        }

        $cuentaNumero = null;

        if ($payment->membershipAccount) {
            $cuentaNumero = $payment->membershipAccount->membership_number;
        }

        $formaPago = null;

        if ($payment->paymentMethod) {
            $formaPago = $payment->paymentMethod->name;
        }

        return [
            'folio' => $payment->folio,
            'folio_corto' => $folioCorto,
            'fecha' => $payment->paid_at,
            'club_nombre' => $clubNombre,
            'club_razon_social_lineas' => $clubRazonSocialLineas,
            'club_direccion_lineas' => $clubDireccionLineas,
            'club_rfc' => $clubRfc,
            'club_url_facturacion' => $clubUrlFacturacion,
            'club_logo_url' => $clubLogoUrl,
            'cajero_nombre' => $cajeroNombre,
            'cajero_codigo' => $cajeroCodigo,
            'cuenta_numero' => $cuentaNumero,
            'titular' => $titular,
            'conceptos' => $conceptos,
            'forma_pago' => $formaPago,
            'referencia' => $payment->reference,
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $payment->amount,
        ];
    }

    private function logoUrlPorClub($clubCode) {
        $mapa = [
            'PE1' => asset('assets/images/LogoP1.png'),
            'PE2' => asset('assets/images/LogoP2.png'),
        ];

        if (isset($mapa[$clubCode])) {
            return $mapa[$clubCode];
        }

        return null;
    }

    private function razonSocialLineas($clubCode) {
        $mapa = [
            'PE1' => ['FUNDACION DEPORTIVO PARQUE ESPAÑA'],
            'PE2' => ['FUNDACION DEPORTIVO PARQUE ESPAÑA II', 'FUNDACION DEPORTIVO PARQUE ESPAÑA'],
        ];

        if (isset($mapa[$clubCode])) {
            return $mapa[$clubCode];
        }

        return [];
    }

    private function calcularFolioCorto($folioCompleto) {
        if (!$folioCompleto) {
            return null;
        }

        $partes = explode('-', $folioCompleto);

        if (count($partes) !== 3) {
            return null;
        }

        $codigo = $partes[0];
        $fecha = $partes[1];
        $consecutivo = $partes[2];

        $dia = substr($fecha, 4, 2);

        return $codigo . '-' . $dia . $consecutivo;
    }

    private function armarLineasDireccion($clubAddress) {
        $lineas = [];

        if (!$clubAddress) {
            return $lineas;
        }

        if ($clubAddress->street) {
            $lineas[] = $clubAddress->street;
        }

        $lineaColoniaCp = '';

        if ($clubAddress->neighborhood) {
            $lineaColoniaCp = 'Col. ' . $clubAddress->neighborhood;
        }

        if ($clubAddress->postal_code) {
            if ($lineaColoniaCp !== '') {
                $lineaColoniaCp .= ' ';
            }

            $lineaColoniaCp .= 'CP ' . $clubAddress->postal_code;
        }

        if ($lineaColoniaCp !== '') {
            $lineas[] = $lineaColoniaCp;
        }

        $lineaCiudadEstado = '';

        if ($clubAddress->city) {
            $lineaCiudadEstado = $clubAddress->city->name;
        }

        if ($clubAddress->state) {
            if ($lineaCiudadEstado !== '') {
                $lineaCiudadEstado .= ' ';
            }

            $lineaCiudadEstado .= $clubAddress->state->name;
        }

        if ($lineaCiudadEstado !== '') {
            $lineas[] = $lineaCiudadEstado . ' México';
        }

        return $lineas;
    }
}
