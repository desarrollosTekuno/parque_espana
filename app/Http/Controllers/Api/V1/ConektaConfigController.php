<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\PaymentMethod;
use Illuminate\Http\JsonResponse;

class ConektaConfigController extends Controller
{
    /**
     * GET /api/v1/clubs/{club}/conekta-public-key
     *
     * Llave pública de Conekta de la cuenta comercial de este club, para que
     * el cliente tokenice tarjetas del lado del dispositivo antes de mandar
     * el token a POST /clubs/{club}/payment-sources. Cada parque es una
     * cuenta Conekta independiente (llaves distintas), y no se expone en el
     * login/perfil a propósito: si la llave rota, un token de sesión que no
     * expira no debe quedarse con un valor obsoleto — pedirla justo antes de
     * tokenizar.
     */
    public function publicKey(int $club): JsonResponse
    {
        $clubPaymentMethod = ClubPaymentMethod::query()
            ->where('club_id', $club)
            ->whereHas('paymentMethod', fn ($q) => $q->where('provider', PaymentMethod::PROVIDER_CONEKTA))
            ->first();

        $publicKey = $clubPaymentMethod?->conekta_public_key ?: config('conekta.public_key');

        if (!$publicKey) {
            return $this->unprocessable('El pago con tarjeta no está configurado para este club.');
        }

        return $this->ok(['public_key' => $publicKey]);
    }
}
