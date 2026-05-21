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

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/payment-sources
    // Lista las tarjetas guardadas del miembro autenticado
    // ──────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->memberNotFound();

        $sources = $this->conekta->listPaymentSources($member)
            ->map(fn (MemberPaymentSource $s) => $this->formatSource($s));

        return response()->json([
            'success' => true,
            'data'    => $sources,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/payment-sources
    // Agrega una tarjeta tokenizada desde Flutter
    //
    // Body: { "token_id": "tok_xxx", "set_default": true }
    // ──────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->memberNotFound();

        $validated = $request->validate([
            'token_id'    => ['required', 'string'],
            'set_default' => ['boolean'],
        ]);

        try {
            $source = $this->conekta->addPaymentSource(
                member:     $member,
                tokenId:    $validated['token_id'],
                setDefault: $validated['set_default'] ?? false,
            );

            return response()->json([
                'success' => true,
                'message' => 'Tarjeta agregada correctamente.',
                'data'    => $this->formatSource($source),
            ], 201);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo agregar la tarjeta. Verifica los datos e intenta de nuevo.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /api/v1/payment-sources/{source}
    // Elimina una tarjeta guardada
    // ──────────────────────────────────────────────────────────────
    public function destroy(Request $request, MemberPaymentSource $source): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->memberNotFound();

        // Solo puede eliminar sus propias tarjetas
        if ($source->member_id !== $member->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        try {
            $this->conekta->deletePaymentSource($member, $source);

            return response()->json([
                'success' => true,
                'message' => 'Tarjeta eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar la tarjeta.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // PATCH /api/v1/payment-sources/{source}/set-default
    // Marca una tarjeta como predeterminada
    // ──────────────────────────────────────────────────────────────
    public function setDefault(Request $request, MemberPaymentSource $source): JsonResponse
    {
        $member = $this->resolveAuthMember($request);
        if (!$member) return $this->memberNotFound();

        if ($source->member_id !== $member->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $this->conekta->setDefaultPaymentSource($member, $source);

        return response()->json([
            'success' => true,
            'message' => 'Tarjeta predeterminada actualizada.',
            'data'    => $this->formatSource($source->fresh()),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function resolveAuthMember(Request $request): ?Member
    {
        return Member::where('user_id', $request->user()->id)->first();
    }

    private function memberNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No se encontró un perfil de socio asociado a este usuario.',
        ], 404);
    }

    private function formatSource(MemberPaymentSource $source): array
    {
        return [
            'id'             => $source->id,
            'brand'          => $source->card_brand,
            'last4'          => $source->card_last4,
            'exp_month'      => $source->card_exp_month,
            'exp_year'       => $source->card_exp_year,
            'cardholder'     => $source->cardholder_name,
            'is_default'     => $source->is_default,
            'created_at'     => $source->created_at?->toDateString(),
        ];
    }
}
