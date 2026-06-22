<?php

namespace App\Services\Migration\Importers;

use App\Models\Billing\Charge;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HistorialImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Historial por Período';
    }

    public function sheetIndex(): int
    {
        return 7; // "8. Historial por Período"
    }

    public function import(
        Worksheet $sheet,
        MigrationContext $context,
        MigrationReport $report,
        bool $dryRun
    ): void {
        $ok      = 0;
        $errors  = [];
        $columns = $this->buildColumnMap($sheet);

        // Método de pago por defecto para filas sin METODO_PAGO o con código desconocido.
        $fallbackPaymentMethodId = !empty($context->paymentMethodsByCode)
            ? array_values($context->paymentMethodsByCode)[0]
            : null;

        // ── Paso 1: leer todas las filas y crear los cargos ───────────────────
        $chargesByRow   = []; // rowNum → charge_id
        $pendingPayments = []; // PAGO_REF → [ ['charge_id', 'amount', 'payment_data'] ]

        foreach ($this->rows($sheet, $columns) as $rowNum => $row) {
            try {
                $noCuenta     = trim($row['NO_CUENTA'] ?? '');
                $parqueCode   = strtoupper(trim($row['PARQUE'] ?? ''));
                $conceptCode  = strtoupper(trim($row['CONCEPTO_CODIGO'] ?? ''));
                $montoStr     = $row['MONTO_CARGO'] ?? null;

                if (empty($noCuenta) || empty($conceptCode) || empty($montoStr)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'NO_CUENTA, CONCEPTO_CODIGO o MONTO_CARGO vacíos.'];
                    continue;
                }

                $accountId = $context->accountId($noCuenta);
                if (!$accountId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Cuenta '{$noCuenta}' no encontrada."];
                    continue;
                }

                $clubId = $context->clubId($parqueCode);
                if (!$clubId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Parque '{$parqueCode}' no encontrado."];
                    continue;
                }

                $conceptId = $context->conceptId($conceptCode);
                if (!$conceptId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Concepto '{$conceptCode}' no encontrado en catálogo."];
                    continue;
                }

                $montoCargo   = $this->parseDecimal($montoStr);
                $montoPagado  = $this->parseDecimal($row['MONTO_PAGADO'] ?? null);
                $periodYear   = $this->parseInt($row['AÑO'] ?? null);
                $periodMonth  = $this->parseInt($row['MES'] ?? null);
                $dueDate      = $this->parseDate($row['FECHA_VENCIMIENTO'] ?? null)
                    ?? $this->deriveDueDate($periodYear, $periodMonth);
                $issueDate    = $dueDate; // fecha de emisión = fecha de vencimiento si no se especifica
                $descripcion  = $row['DESCRIPCION'] ?? null;
                $pagoRef      = trim($row['PAGO_REF'] ?? '');
                $notas        = $row['NOTAS'] ?? null;

                // Calcular balance inicial
                $balance    = $montoPagado !== null
                    ? max(0, $montoCargo - $montoPagado)
                    : $montoCargo;

                $chargeStatus = match (true) {
                    $balance <= 0                           => 'paid',
                    $montoPagado > 0 && $balance > 0        => 'partial',
                    default                                 => 'pending',
                };

                // membership_id para el cargo
                $membershipId = $context->membershipId($noCuenta, $clubId);

                // member_id del titular para el cargo
                $memberId = $context->primaryHolderByAccount[$noCuenta] ?? null;

                $allowsPartial = $context->conceptAllowsPartial[$conceptId] ?? false;

                if (!$dryRun) {
                    $charge = Charge::create([
                        'membership_account_id'   => $accountId,
                        'membership_id'           => $membershipId,
                        'member_id'               => $memberId,
                        'concept_id'              => $conceptId,
                        'description'             => $descripcion,
                        'amount'                  => $montoCargo,
                        'balance'                 => $balance,
                        'issue_date'              => $issueDate,
                        'due_date'                => $dueDate,
                        'period_year'             => $periodYear,
                        'period_month'            => $periodMonth,
                        'allows_partial_payments' => $allowsPartial,
                        'status'                  => $chargeStatus,
                        'metadata'                => ['imported' => true, 'notes' => $notas],
                    ]);

                    $chargesByRow[$rowNum] = $charge->id;
                } else {
                    $chargesByRow[$rowNum] = 0;
                }

                // Si hay pago, agrupar o registrar directo.
                // Si el concepto no admite parcialidades, el pago cubre el cargo completo.
                if ($montoPagado !== null && $montoPagado > 0) {
                    $resolvedPaymentMethodId = $context->paymentMethodId($row['METODO_PAGO'] ?? '')
                        ?? $fallbackPaymentMethodId;

                    if (!$resolvedPaymentMethodId) {
                        $errors[] = ['row' => $rowNum, 'message' => 'No hay métodos de pago en catálogo para registrar el pago.'];
                        $ok++;
                        continue;
                    }

                    // Sin parcialidades: el pago siempre es por el monto total del cargo
                    $effectivePaidAmount = !$allowsPartial ? $montoCargo : $montoPagado;

                    $paymentData = [
                        'membership_account_id' => $accountId,
                        'club_id'               => $clubId,
                        'payment_method_id'     => $resolvedPaymentMethodId,
                        'paid_at'               => $this->parseDate($row['FECHA_PAGO'] ?? null) ?? now()->toDateString(),
                        'reference'             => $row['REFERENCIA'] ?? null,
                        'bank_name'             => $row['BANCO'] ?? null,
                        'check_number'          => $row['NUM_CHEQUE'] ?? null,
                        'notes'                 => $notas,
                        'metadata'              => ['imported' => true],
                    ];

                    if (!empty($pagoRef) && $allowsPartial) {
                        // Solo agrupar pagos si el concepto admite parcialidades
                        $pendingPayments[$pagoRef][] = [
                            'row_num'      => $rowNum,
                            'charge_id'    => $chargesByRow[$rowNum],
                            'amount'       => $effectivePaidAmount,
                            'payment_data' => $paymentData,
                        ];
                    } else {
                        // Pago 1 a 1: un único pago por cargo
                        if (!$dryRun) {
                            $this->createPaymentWithApplication(
                                $paymentData,
                                $effectivePaidAmount,
                                $chargesByRow[$rowNum]
                            );
                        }
                    }
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        // ── Paso 2: crear pagos agrupados por PAGO_REF ────────────────────────
        if (!$dryRun) {
            foreach ($pendingPayments as $pagoRef => $items) {
                try {
                    $totalAmount  = array_sum(array_column($items, 'amount'));
                    $paymentData  = $items[0]['payment_data']; // mismos datos para todo el grupo
                    $paymentData['amount'] = $totalAmount;

                    $payment = Payment::create($paymentData);

                    foreach ($items as $item) {
                        PaymentApplication::create([
                            'payment_id'     => $payment->id,
                            'charge_id'      => $item['charge_id'],
                            'applied_amount' => $item['amount'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['row' => 0, 'message' => "PAGO_REF '{$pagoRef}': {$e->getMessage()}"];
                }
            }
        }

        $report->record($this->name(), $ok, $errors);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function createPaymentWithApplication(
        array $paymentData,
        float $amount,
        int $chargeId
    ): void {
        $payment = Payment::create(array_merge($paymentData, ['amount' => $amount]));

        PaymentApplication::create([
            'payment_id'     => $payment->id,
            'charge_id'      => $chargeId,
            'applied_amount' => $amount,
        ]);
    }

    /**
     * Deriva la fecha de vencimiento del último día del mes del período.
     * Ej: año=2024, mes=3 → 2024-03-31
     */
    private function deriveDueDate(?int $year, ?int $month): ?string
    {
        if (!$year) {
            return null;
        }

        if (!$month) {
            // Concepto puntual (inscripción, casillero): usa el 31 de enero del año
            return Carbon::create($year, 1, 31)->toDateString();
        }

        return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
    }
}
