<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use App\Services\Payments\ConektaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSourceController extends Controller
{
    public function __construct(private ConektaService $conekta) {}

    /**
     * GET /api/v1/clubs/{club}/payment-sources
     */
    public function index(Request $request, int $club): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');

        $sources = $this->conekta->listPaymentSources($member, $club)
            ->map(fn (MemberPaymentSource $s) => $this->formatSource($s));

        return $this->ok($sources);
    }

    /**
     * POST /api/v1/clubs/{club}/payment-sources
     */
    public function store(Request $request, int $club): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');

        $validated = $request->validate([
            'token_id'    => ['required', 'string'],
            'set_default' => ['boolean'],
        ]);

        try {
            $source = $this->conekta->addPaymentSource(
                member:     $member,
                clubId:     $club,
                tokenId:    $validated['token_id'],
                setDefault: $validated['set_default'] ?? false,
            );

            return $this->created('Tarjeta agregada correctamente.', $this->formatSource($source));
        } catch (\Throwable $e) {
            report($e);
            return $this->unprocessable('No se pudo agregar la tarjeta. Verifica los datos e intenta de nuevo.');
        }
    }

    /**
     * DELETE /api/v1/clubs/{club}/payment-sources/{source}
     */
    public function destroy(Request $request, int $club, MemberPaymentSource $source): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');

        if ($source->member_id !== $member->id || (int) $source->club_id !== $club) {
            return $this->forbidden('No autorizado.');
        }

        try {
            $this->conekta->deletePaymentSource($member, $club, $source);

            return $this->success('Tarjeta eliminada correctamente.');
        } catch (\Throwable $e) {
            report($e);
            return $this->unprocessable('No se pudo eliminar la tarjeta.');
        }
    }

    /**
     * PATCH /api/v1/clubs/{club}/payment-sources/{source}/set-default
     */
    public function setDefault(Request $request, int $club, MemberPaymentSource $source): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');

        if ($source->member_id !== $member->id || (int) $source->club_id !== $club) {
            return $this->forbidden('No autorizado.');
        }

        $this->conekta->setDefaultPaymentSource($member, $club, $source);

        return $this->success('Tarjeta predeterminada actualizada.', $this->formatSource($source->fresh()));
    }

    private function resolveAuthMember(Request $request): ?Member
    {
        return Member::where('user_id', $request->user()->id)->first();
    }

    private function formatSource(MemberPaymentSource $source): array
    {
        return [
            'id'         => $source->id,
            'brand'      => $source->card_brand,
            'last4'      => $source->card_last4,
            'exp_month'  => $source->card_exp_month,
            'exp_year'   => $source->card_exp_year,
            'cardholder' => $source->cardholder_name,
            'is_default' => $source->is_default,
            'created_at' => $source->created_at?->toDateString(),
        ];
    }
}
