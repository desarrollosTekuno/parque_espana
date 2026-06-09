<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Members\Member;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountStatementController extends Controller
{
    private const MONTH_NAMES = [
        1 => 'Enero',  2 => 'Febrero',   3 => 'Marzo',    4 => 'Abril',
        5 => 'Mayo',   6 => 'Junio',     7 => 'Julio',     8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * GET /api/v1/clubs/{club}/account-statement
     *
     * Estado de cuenta del socio titular. Devuelve todos los tipos de cargo
     * sin restricción de concepto — lo que exista en billing.concepts se mostrará.
     *
     * Ejecuta 2 queries:
     *   1. GROUP BY para resumen/semáforo (sin traer filas a PHP)
     *   2. simplePaginate con JOIN (sin COUNT(*))
     *
     * Query params:
     *   period   "month" | "quarter" | "year"   (default: "year")
     *   year     int                             (default: año actual)
     *   month    int 1-12   requerido si period=month
     *   quarter  int 1-4    requerido si period=quarter
     *   per_page int 1-50   (default: 15)
     */
    public function show(Request $request, Club $club): JsonResponse
    {
        $request->validate([
            'period'   => ['sometimes', 'in:month,quarter,year'],
            'year'     => ['sometimes', 'integer', 'min:2020', 'max:' . (now()->year + 1)],
            'month'    => ['required_if:period,month', 'nullable', 'integer', 'min:1', 'max:12'],
            'quarter'  => ['required_if:period,quarter', 'nullable', 'integer', 'min:1', 'max:4'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'filter'   => ['sometimes', 'nullable', 'in:pending,paid'],
        ]);

        // ── 1. Socio ──────────────────────────────────────────────────────
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil de socio asociado a este usuario.',
            ], 404);
        }

        // ── 2. Titularidad ────────────────────────────────────────────────
        $accountMember = $member->accountMemberships()
            ->with('membershipAccount')
            ->whereHas('membershipAccount.memberships', fn($q) => $q
                ->where('club_id', $club->id)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();

        if (!$accountMember) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes una membresía activa en este club.',
            ], 403);
        }

        if (!$accountMember->is_primary_holder) {
            return response()->json([
                'success' => false,
                'message' => 'Solo el socio titular puede consultar el estado de cuenta.',
            ], 403);
        }

        // ── 3. Parámetros ─────────────────────────────────────────────────
        $account = $accountMember->membershipAccount;
        $params  = $this->resolvePeriodParams($request);
        $perPage = (int) $request->input('per_page', 15);
        $filter  = $request->input('filter');          // null | 'pending' | 'paid'

        // ── Query 1: resumen agregado (siempre sin filtro de status) ──────
        // El semáforo y el resumen reflejan el periodo completo
        // independientemente del filtro activo en la lista.
        $summary   = $this->fetchSummary($account->id, $params);
        $semaforo  = $this->calculateSemaforo($summary);
        $totalOwed = round((float) $summary->sum('total_pending'), 2);

        // ── Query 2: cargos paginados (con filtro opcional de status) ─────
        $paginator = $this->fetchCharges($account->id, $params, $perPage, $filter);

        return response()->json([
            'success' => true,
            'data'    => [
                'period'     => $this->buildPeriodMeta($params),
                'filter'     => $filter,               // null = todos
                'semaforo'   => $semaforo,
                'total_owed' => $totalOwed,
                'summary'    => $this->formatSummary($summary),
                'charges'    => [
                    'data' => collect($paginator->items())
                        ->map(fn($c) => $this->formatCharge($c)),
                    'meta' => [
                        'current_page'   => $paginator->currentPage(),
                        'per_page'       => $paginator->perPage(),
                        'has_more_pages' => $paginator->hasMorePages(),
                    ],
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resumen por tipo de cargo mediante GROUP BY + CASE WHEN.
     * Devuelve una fila por concepto que tenga cargos en el periodo.
     */
    private function fetchSummary(int $accountId, array $params): \Illuminate\Support\Collection
    {
        return DB::table('billing.charges as c')
            ->join('billing.concepts as con', 'con.id', '=', 'c.concept_id')
            ->where('c.membership_account_id', $accountId)
            ->whereNotIn('c.status', ['cancelled'])
            ->where(fn($q) => $this->applyPeriodFilter($q, $params, 'c'))
            ->groupBy('con.code', 'con.name')
            ->selectRaw("
                con.code                                                                                                          AS concept_code,
                con.name                                                                                                          AS concept_name,
                SUM(c.amount)                                                                                                     AS total_charges,
                SUM(CASE WHEN c.status = 'paid'                                           THEN c.amount  ELSE 0 END)             AS total_paid,
                SUM(CASE WHEN c.status IN ('pending','partial')                           THEN c.balance ELSE 0 END)             AS total_pending,
                SUM(CASE WHEN c.status IN ('pending','partial') AND c.due_date < CURRENT_DATE THEN c.balance ELSE 0 END)         AS total_overdue
            ")
            ->get();
    }

    /**
     * Cargos paginados con JOIN directo al concepto (sin query extra de relación).
     * simplePaginate: no ejecuta COUNT(*).
     *
     * @param string|null $filter  null = todos | 'pending' = pendientes+vencidos | 'paid' = pagados
     */
    private function fetchCharges(int $accountId, array $params, int $perPage, ?string $filter): \Illuminate\Pagination\Paginator
    {
        $query = Charge::query()
            ->select(
                'billing.charges.*',
                'con.code as concept_code',
                'con.name as concept_name',
            )
            ->join('billing.concepts as con', 'con.id', '=', 'billing.charges.concept_id')
            ->where('billing.charges.membership_account_id', $accountId)
            ->whereNotIn('billing.charges.status', ['cancelled'])
            ->where(fn($q) => $this->applyPeriodFilter($q, $params, 'billing.charges'));

        // ── Filtro de status ──────────────────────────────────────────────
        match ($filter) {
            'pending' => $query->whereIn('billing.charges.status', ['pending', 'partial']),
            'paid'    => $query->where('billing.charges.status', 'paid'),
            default   => null,   // sin filtro adicional
        };

        // Los pagados se ordenan por due_date desc (más reciente primero).
        // Los pendientes/todos se ordenan por due_date asc (los que vencen
        // antes aparecen primero, más útil para el socio).
        $order = $filter === 'paid' ? 'desc' : 'asc';

        return $query
            ->orderBy('billing.charges.due_date', $order)
            ->orderBy('billing.charges.id', $order)
            ->simplePaginate($perPage);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtro de periodo (compartido entre ambas queries)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Estrategia de filtro:
     *  - Cargos CON period_year/month → se filtran por esos campos (semánticamente correcto)
     *  - Cargos SIN period_year       → se filtran por issue_date dentro del rango
     *
     * Esto cubre cualquier tipo de cargo sin necesidad de conocer sus códigos de concepto.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $table  Prefijo de tabla (ej. "c" o "billing.charges")
     */
    private function applyPeriodFilter($query, array $params, string $table): void
    {
        $query->where(function ($q) use ($params, $table) {
            // Rama 1: cargos con periodo explícito (ej. mensualidades)
            $q->orWhere(function ($inner) use ($params, $table) {
                $inner->whereNotNull("{$table}.period_year")
                      ->where("{$table}.period_year", $params['year'])
                      ->whereIn("{$table}.period_month", $params['months']);
            });

            // Rama 2: cargos sin periodo — usar issue_date
            $q->orWhere(function ($inner) use ($params, $table) {
                $inner->whereNull("{$table}.period_year")
                      ->whereBetween("{$table}.issue_date", [
                          $params['from']->toDateString(),
                          $params['to']->toDateString(),
                      ]);
            });
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formateo
    // ─────────────────────────────────────────────────────────────────────────

    private function formatCharge(Charge $charge): array
    {
        $isOverdue = $this->isOverdue($charge);

        return [
            'id'             => $charge->id,
            'type'           => $charge->concept_code,
            'type_label'     => $charge->concept_name,
            'description'    => $charge->description ?? $charge->concept_name,
            'period_year'    => $charge->period_year,
            'period_month'   => $charge->period_month,
            'period_label'   => $this->periodLabel($charge->period_year, $charge->period_month),
            'issue_date'     => $charge->issue_date,
            'due_date'       => $charge->due_date,
            'amount'         => $charge->amount,
            'balance'        => $charge->balance,
            'display_status' => match (true) {
                $charge->status === 'paid' => 'paid',
                $isOverdue                 => 'overdue',
                default                    => 'pending',
            },
        ];
    }

    /**
     * Formatea el resumen. Incluye solo los tipos que tienen cargos en el periodo
     * (no hay tipos hardcodeados — si mañana se agrega un nuevo concepto aparece solo).
     */
    private function formatSummary(\Illuminate\Support\Collection $rows): array
    {
        return $rows->map(fn($row) => [
            'type'          => $row->concept_code,
            'type_label'    => $row->concept_name,
            'total_charges' => round((float) $row->total_charges, 2),
            'total_paid'    => round((float) $row->total_paid, 2),
            'total_pending' => round((float) $row->total_pending, 2),
            'total_overdue' => round((float) $row->total_overdue, 2),
        ])->values()->toArray();
    }

    private function calculateSemaforo(\Illuminate\Support\Collection $summary): string
    {
        if ($summary->contains(fn($r) => (float) $r->total_overdue > 0)) {
            return 'red';
        }

        if ($summary->contains(fn($r) => (float) $r->total_pending > 0)) {
            return 'yellow';
        }

        return 'green';
    }

    private function isOverdue(Charge $charge): bool
    {
        return in_array($charge->status, ['pending', 'partial'])
            && $charge->due_date !== null
            && Carbon::parse($charge->due_date)->lt(today());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Periodo
    // ─────────────────────────────────────────────────────────────────────────

    private function resolvePeriodParams(Request $request): array
    {
        $period = $request->input('period', 'year');
        $year   = (int) $request->input('year', now()->year);

        if ($period === 'month') {
            $month = (int) $request->month;
            return [
                'period' => 'month',
                'year'   => $year,
                'months' => [$month],
                'from'   => Carbon::create($year, $month, 1)->startOfMonth(),
                'to'     => Carbon::create($year, $month, 1)->endOfMonth(),
            ];
        }

        if ($period === 'quarter') {
            $quarter    = (int) $request->quarter;
            $firstMonth = ($quarter - 1) * 3 + 1;
            return [
                'period'  => 'quarter',
                'year'    => $year,
                'quarter' => $quarter,
                'months'  => [$firstMonth, $firstMonth + 1, $firstMonth + 2],
                'from'    => Carbon::create($year, $firstMonth, 1)->startOfMonth(),
                'to'      => Carbon::create($year, $firstMonth + 2, 1)->endOfMonth(),
            ];
        }

        return [
            'period' => 'year',
            'year'   => $year,
            'months' => range(1, 12),
            'from'   => Carbon::create($year, 1, 1)->startOfYear(),
            'to'     => Carbon::create($year, 12, 31)->endOfYear(),
        ];
    }

    private function buildPeriodMeta(array $params): array
    {
        $meta = ['type' => $params['period'], 'year' => $params['year']];

        if ($params['period'] === 'month') {
            $month         = $params['months'][0];
            $meta['month'] = $month;
            $meta['label'] = (self::MONTH_NAMES[$month] ?? '') . ' ' . $params['year'];
        } elseif ($params['period'] === 'quarter') {
            $meta['quarter'] = $params['quarter'];
            $meta['label']   = sprintf(
                'T%d %d (%s – %s)',
                $params['quarter'], $params['year'],
                self::MONTH_NAMES[$params['months'][0]] ?? '',
                self::MONTH_NAMES[$params['months'][2]] ?? ''
            );
        } else {
            $meta['label'] = (string) $params['year'];
        }

        return $meta;
    }

    private function periodLabel(?int $year, ?int $month): ?string
    {
        if (!$year || !$month) {
            return null;
        }
        return (self::MONTH_NAMES[$month] ?? '') . ' ' . $year;
    }
}
