<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\ChargeConceptClubAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BillingConceptController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:billing-concepts.index')->only('index');
        $this->middleware('permission:billing-concepts.store')->only('store');
        $this->middleware('permission:billing-concepts.update')->only('update');
        $this->middleware('permission:billing-concepts.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));
            $driver = DB::getDriverName();
            $prefix = 'billingConcepts';

            $query = ChargeConcept::query()
                ->with([
                    'clubAmounts' => fn ($clubAmountQuery) => $clubAmountQuery
                        ->where('club_id', $clubId)
                        ->where('is_active', true),
                ]);

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('code', $like, "%{$search}%")
                        ->orWhere('internal_key', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%");
                });
            }

            if (($activeOnly = $request->input("{$prefix}_is_active")) !== null && $activeOnly !== '') {
                $query->where('is_active', filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN));
            }

            $sortMap = [
                'id' => 'id',
                'code' => 'code',
                'name' => 'name',
                'default_amount' => 'default_amount',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'name');
            $order = $request->input("{$prefix}_order", 'asc');
            $sortColumn = $sortMap[$sort] ?? 'name';

            $billingConcepts = $query
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (ChargeConcept $concept) {
                    $clubAmount = $concept->clubAmounts->first();

                    return [
                        'id' => $concept->id,
                        'code' => $concept->code,
                        'internal_key' => $concept->internal_key,
                        'name' => $concept->name,
                        'description' => $concept->description,
                        'default_amount' => $concept->default_amount !== null ? (float) $concept->default_amount : null,
                        'club_amount' => $clubAmount?->amount !== null ? (float) $clubAmount->amount : null,
                        'allows_manual_amount' => (bool) $concept->allows_manual_amount,
                        'is_recurring' => (bool) $concept->is_recurring,
                        'allows_partial_payments' => (bool) $concept->allows_partial_payments,
                        'is_mobile_payable' => (bool) $concept->is_mobile_payable,
                        'splits_between_parks' => (bool) $concept->splits_between_parks,
                        // applies_iva: si aplica IVA por default (sin override
                        // de parque); club_applies_iva: el override específico
                        // para el parque en sesión (null = usa el default).
                        'applies_iva' => (bool) $concept->applies_iva,
                        'club_applies_iva' => $clubAmount?->applies_iva,
                        'is_active' => (bool) $concept->is_active,
                        'requires_account' => (bool) $concept->requires_account,
                    ];
                })
                ->appends($request->all());

            $currentClub = Club::query()
                ->select('id', 'name', 'code')
                ->find($clubId);

            return Inertia::render('AdminClubs/BillingConcepts/Index', [
                'billingConcepts' => $billingConcepts,
                'currentClub' => $currentClub ? [
                    'id' => $currentClub->id,
                    'name' => $currentClub->name,
                    'code' => $currentClub->code,
                ] : null,
                'filters' => [
                    'search' => $request->input("{$prefix}_search"),
                    'is_active' => $request->input("{$prefix}_is_active"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/BillingConcepts/Index', [
                'billingConcepts' => [
                    'data' => [],
                    'total' => 0,
                ],
                'currentClub' => null,
                'filters' => [
                    'search' => $request->input('billingConcepts_search'),
                    'is_active' => $request->input('billingConcepts_is_active'),
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateConcept($request);

            DB::transaction(function () use ($validated) {
                $concept = ChargeConcept::create($this->conceptAttributes($validated));
                $this->syncClubAmount($concept, (int) session('club_id'), $validated);
            });

            return redirect()->back()->with('success', 'Concepto de cobro creado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el concepto de cobro.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, ChargeConcept $billingConcept)
    {
        try {
            $validated = $this->validateConcept($request, $billingConcept);

            DB::transaction(function () use ($billingConcept, $validated) {
                $billingConcept->update($this->conceptAttributes($validated));
                $this->syncClubAmount($billingConcept, (int) session('club_id'), $validated);
            });

            return redirect()->back()->with('success', 'Concepto de cobro actualizado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el concepto de cobro.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(ChargeConcept $billingConcept)
    {
        try {
            $billingConcept->delete();

            return redirect()->back()->with('success', 'Concepto de cobro eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el concepto de cobro.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validateConcept(Request $request, ?ChargeConcept $concept = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(ChargeConcept::class, 'code')->ignore($concept?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'internal_key' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'club_amount' => ['nullable', 'numeric', 'min:0'],
            'allows_manual_amount' => ['required', 'boolean'],
            'is_recurring' => ['required', 'boolean'],
            'allows_partial_payments' => ['required', 'boolean'],
            'is_mobile_payable' => ['required', 'boolean'],
            'splits_between_parks' => ['required', 'boolean'],
            'applies_iva' => ['required', 'boolean'],
            // null = sin override, usa applies_iva del concepto para el
            // parque en sesión.
            'club_applies_iva' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'requires_account' => ['required', 'boolean'],
        ]);
    }

    protected function conceptAttributes(array $validated): array
    {
        return [
            'code' => strtoupper(trim($validated['code'])),
            'internal_key' => isset($validated['internal_key']) ? trim($validated['internal_key']) ?: null : null,
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'default_amount' => $validated['default_amount'] !== null ? (float) $validated['default_amount'] : null,
            'is_recurring' => (bool) $validated['is_recurring'],
            'allows_partial_payments' => (bool) $validated['allows_partial_payments'],
            'is_mobile_payable' => (bool) $validated['is_mobile_payable'],
            'splits_between_parks' => (bool) $validated['splits_between_parks'],
            'applies_iva' => (bool) $validated['applies_iva'],
            'is_active' => (bool) $validated['is_active'],
            'requires_account' => (bool) $validated['requires_account'],
        ];
    }

    /**
     * El renglón de billing.concept_club_amounts para (concepto, parque) ya
     * no es solo "el monto de este parque": también puede cargar el
     * override de applies_iva para ese parque. Por eso solo se borra cuando
     * NINGUNO de los dos está definido (monto vacío y sin override de IVA);
     * si cualquiera de los dos tiene valor, se conserva el renglón.
     */
    protected function syncClubAmount(ChargeConcept $concept, int $clubId, array $validated): void
    {
        $clubAmount = $validated['club_amount'] ?? null;
        $clubAppliesIva = $validated['club_applies_iva'] ?? null;

        if (($clubAmount === null || $clubAmount === '') && $clubAppliesIva === null) {
            $concept->clubAmounts()
                ->where('club_id', $clubId)
                ->delete();

            return;
        }

        ChargeConceptClubAmount::updateOrCreate(
            [
                'concept_id' => $concept->id,
                'club_id' => $clubId,
            ],
            [
                'amount' => ($clubAmount === null || $clubAmount === '') ? null : (float) $clubAmount,
                'applies_iva' => $clubAppliesIva === null ? null : (bool) $clubAppliesIva,
                'is_active' => true,
            ]
        );
    }
}
