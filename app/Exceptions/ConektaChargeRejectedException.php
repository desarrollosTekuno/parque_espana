<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Conekta rechazó (o no confirmó) el cobro — se lanza dentro de la
 * transacción de ChargePaymentController::processSplitPayment() para que se
 * revierta la partición de cargos (InterclubSplitPaymentService::splitCharge)
 * junto con el rechazo, en vez de dejar un cargo espejo huérfano sin dinero
 * real detrás.
 */
class ConektaChargeRejectedException extends RuntimeException
{
    public function __construct(public readonly string $conektaStatus)
    {
        parent::__construct("Cobro Conekta no confirmado (status: {$conektaStatus}).");
    }
}
