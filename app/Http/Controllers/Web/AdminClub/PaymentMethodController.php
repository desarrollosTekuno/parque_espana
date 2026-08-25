<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:payment-methods.index')->only('index');
        $this->middleware('permission:payment-methods.store')->only('store');
        $this->middleware('permission:payment-methods.update')->only(['update', 'toggleClub', 'updateClubConfig']);
        $this->middleware('permission:payment-methods.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));
            $driver = DB::getDriverName();
            $prefix = 'paymentMethods';

            $query = PaymentMethod::query()
                ->with([
                    'clubPaymentMethods' => fn ($q) => $q->where('club_id', $clubId),
                ]);

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('code', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%")
                        ->orWhereHas('clubPaymentMethods', function (Builder $q) use ($search, $like) {
                            $q->where('internal_key', $like, "%{$search}%");
                        });
                });
            }

            if (($activeOnly = $request->input("{$prefix}_is_active")) !== null && $activeOnly !== '') {
                $query->where('is_active', filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN));
            }

            $sortMap = [
                'id' => 'id',
                'code' => 'code',
                'name' => 'name',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'name');
            $order = $request->input("{$prefix}_order", 'asc');
            $sortColumn = $sortMap[$sort] ?? 'name';

            $paymentMethods = $query
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (PaymentMethod $method) {
                    $clubMethod = $method->clubPaymentMethods->first();

                    return [
                        'id' => $method->id,
                        'code' => $method->code,
                        'provider' => $method->provider,
                        'name' => $method->name,
                        'description' => $method->description,
                        'requires_reference' => $method->requires_reference,
                        'requires_bank_name' => $method->requires_bank_name,
                        'requires_check_number' => $method->requires_check_number,
                        'affects_cash_cut' => $method->affects_cash_cut,
                        'is_active' => $method->is_active,
                        'show_in_billing' => $method->show_in_billing,
                        'club_enabled' => $clubMethod !== null,
                        'club_display_order' => $clubMethod?->display_order ?? 0,
                        'club_internal_key' => $clubMethod?->internal_key,
                    ];
                })
                ->appends($request->all());

            $currentClub = Club::query()
                ->select('id', 'name', 'code')
                ->find($clubId);

            return Inertia::render('AdminClubs/PaymentMethods/Index', [
                'paymentMethods' => $paymentMethods,
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

            return Inertia::render('AdminClubs/PaymentMethods/Index', [
                'paymentMethods' => ['data' => [], 'total' => 0],
                'currentClub' => null,
                'filters' => [
                    'search' => $request->input('paymentMethods_search'),
                    'is_active' => $request->input('paymentMethods_is_active'),
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateMethod($request);

            PaymentMethod::create($this->methodAttributes($validated));

            return redirect()->back()->with('success', 'Método de pago creado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el método de pago.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        try {
            $validated = $this->validateMethod($request, $paymentMethod);

            $paymentMethod->update($this->methodAttributes($validated));

            return redirect()->back()->with('success', 'Método de pago actualizado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el método de pago.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        try {
            $paymentMethod->delete();

            return redirect()->back()->with('success', 'Método de pago eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el método de pago.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function toggleClub(Request $request, PaymentMethod $paymentMethod)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));

            $existing = ClubPaymentMethod::where('club_id', $clubId)
                ->where('payment_method_id', $paymentMethod->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $message = 'Método de pago deshabilitado para este club.';
            } else {
                $maxOrder = ClubPaymentMethod::where('club_id', $clubId)->max('display_order') ?? 0;

                ClubPaymentMethod::create([
                    'club_id' => $clubId,
                    'payment_method_id' => $paymentMethod->id,
                    'is_active' => true,
                    'display_order' => $maxOrder + 1,
                ]);
                $message = 'Método de pago habilitado para este club.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al cambiar el estado del método de pago.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function updateClubConfig(Request $request, PaymentMethod $paymentMethod)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));

            $validated = $request->validate([
                'club_id'      => ['required', 'integer'],
                'internal_key' => [
                    'nullable',
                    'string',
                    'max:100',
                    (new \App\Rules\UniqueInSchema('billing', 'club_payment_methods', 'internal_key'))
                        ->where('club_id', $clubId)
                        ->ignore($paymentMethod->id, 'payment_method_id'),
                ],
            ]);

            ClubPaymentMethod::updateOrCreate(
                [
                    'club_id'           => $clubId,
                    'payment_method_id' => $paymentMethod->id,
                ],
                [
                    'internal_key' => isset($validated['internal_key'])
                        ? trim($validated['internal_key']) ?: null
                        : null,
                ]
            );

            return redirect()->back()->with('success', 'Clave interna actualizada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la clave interna.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validateMethod(Request $request, ?PaymentMethod $method = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(PaymentMethod::class, 'code')->ignore($method?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'provider' => ['nullable', 'string', 'max:50'],
            'requires_reference' => ['required', 'boolean'],
            'requires_bank_name' => ['required', 'boolean'],
            'requires_check_number' => ['required', 'boolean'],
            'affects_cash_cut' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'show_in_billing' => ['required', 'boolean'],
        ]);
    }

    protected function methodAttributes(array $validated): array
    {
        return [
            'code' => strtoupper(trim($validated['code'])),
            'provider' => isset($validated['provider']) ? strtolower(trim($validated['provider'])) : null,
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'requires_reference' => (bool) $validated['requires_reference'],
            'requires_bank_name' => (bool) $validated['requires_bank_name'],
            'requires_check_number' => (bool) $validated['requires_check_number'],
            'affects_cash_cut' => (bool) $validated['affects_cash_cut'],
            'is_active' => (bool) $validated['is_active'],
            'show_in_billing' => (bool) $validated['show_in_billing'],
        ];
    }
}
