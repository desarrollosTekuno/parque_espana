<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Administrator\Club;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ConektaCredentialController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:conekta-credentials.index')->only('index');
        $this->middleware('permission:conekta-credentials.update')->only('update');
    }

    public function index(Request $request)
    {
        $clubIds = Auth::user()->clubs()->pluck('clubs.id');

        $conektaPaymentMethod = PaymentMethod::query()
            ->where('provider', PaymentMethod::PROVIDER_CONEKTA)
            ->first();

        $clubPaymentMethods = $conektaPaymentMethod
            ? ClubPaymentMethod::query()
                ->where('payment_method_id', $conektaPaymentMethod->id)
                ->whereIn('club_id', $clubIds)
                ->get()
                ->keyBy('club_id')
            : collect();

        $clubs = Club::query()
            ->select('id', 'name', 'code')
            ->whereIn('id', $clubIds)
            ->orderBy('name')
            ->get()
            ->map(function (Club $club) use ($clubPaymentMethods) {
                $clubMethod = $clubPaymentMethods->get($club->id);

                return [
                    'club_id' => $club->id,
                    'club_name' => $club->name,
                    'club_code' => $club->code,
                    'conekta_public_key' => $clubMethod?->conekta_public_key,
                    'has_conekta_secret_key' => (bool) $clubMethod?->conekta_secret_key,
                ];
            });

        return Inertia::render('Administrator/ConektaCredentials/Index', [
            'clubs' => $clubs,
            'conektaPaymentMethodExists' => $conektaPaymentMethod !== null,
        ]);
    }

    public function update(Request $request)
    {
        try {
            $conektaPaymentMethod = PaymentMethod::query()
                ->where('provider', PaymentMethod::PROVIDER_CONEKTA)
                ->first();

            if (!$conektaPaymentMethod) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Primero debes crear un método de pago con proveedor Conekta desde "Métodos de pago".',
                ]);
            }

            $validated = $request->validate([
                'club_id' => ['required', 'integer', new \App\Rules\ExistsInSchema('clubs', 'clubs', 'id')],
                'conekta_public_key' => ['nullable', 'string', 'max:255'],
                // Si viene vacía, se conserva la que ya estaba guardada (nunca se
                // manda la llave secreta de vuelta al navegador para editarla).
                'conekta_secret_key' => ['nullable', 'string', 'max:1000'],
            ]);

            $clubId = (int) $validated['club_id'];

            $allowedClubIds = Auth::user()->clubs()->pluck('clubs.id');
            if (!$allowedClubIds->contains($clubId)) {
                return redirect()->back()->withErrors([
                    'messageError' => 'No tienes acceso a ese parque.',
                ]);
            }

            $attributes = [
                'conekta_public_key' => isset($validated['conekta_public_key'])
                    ? trim($validated['conekta_public_key']) ?: null
                    : null,
            ];

            if (filled($validated['conekta_secret_key'] ?? null)) {
                $attributes['conekta_secret_key'] = trim($validated['conekta_secret_key']);
            }

            ClubPaymentMethod::updateOrCreate(
                [
                    'club_id' => $clubId,
                    'payment_method_id' => $conektaPaymentMethod->id,
                ],
                $attributes
            );

            return redirect()->back()->with('success', 'Credenciales de Conekta actualizadas correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar las credenciales de Conekta.',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
