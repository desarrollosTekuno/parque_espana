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

        $fallbackPaymentMethodId = !empty($context->paymentMethodsByCode)
            ? array_values($context->paymentMethodsByCode)[0]
            : null;

        // charge_key → charge_id  (para deduplicar el mismo cargo en múltiples filas)
        $chargeIdByKey     = [];
        // charge_key → monto del cargo  (para calcular el pago en Caso A)
        $chargeAmountByKey = [];
        // Datos completos de cada fila válida
        $parsedRows = [];

        // ── Paso 1: leer filas y crear cargos únicos ──────────────────────────
        foreach ($this->rows($sheet, $columns) as $rowNum => $row) {
            try {
                $noCuenta    = trim($row['NO_CUENTA'] ?? '');
                $parqueCode  = strtoupper(trim($row['PARQUE'] ?? ''));
                $conceptCode = strtoupper(trim($row['CONCEPTO_CODIGO'] ?? ''));
                $montoStr    = $row['MONTO_CARGO'] ?? null;

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
                $pagoRef      = trim($row['PAGO_REF'] ?? '');
                $descripcion  = $row['DESCRIPCION'] ?? null;
                $notas        = $row['NOTAS'] ?? null;

                $membershipId  = $context->membershipId($noCuenta, $clubId);
                $memberId      = $context->primaryHolderByAccount[$noCuenta] ?? null;
                $allowsPartial = $context->conceptAllowsPartial[$conceptId] ?? false;

                // Clave única del cargo: cuenta + club + concepto + período + monto
                // El monto diferencia cargos del mismo concepto/período con distinto importe
                // (ej. cuenta con dos cuotas distintas en el mismo mes)
                $chargeKey = "{$noCuenta}_{$clubId}_{$conceptId}_{$periodYear}_{$periodMonth}_{$montoCargo}";

                if (!isset($chargeIdByKey[$chargeKey])) {
                    if (!$dryRun) {
                        $charge = Charge::create([
                            'membership_account_id'   => $accountId,
                            'membership_id'           => $membershipId,
                            'member_id'               => $memberId,
                            'concept_id'              => $conceptId,
                            'description'             => $descripcion,
                            'amount'                  => $montoCargo,
                            'balance'                 => $montoCargo, // se recalcula en paso 3
                            'issue_date'              => $dueDate,
                            'due_date'                => $dueDate,
                            'period_year'             => $periodYear,
                            'period_month'            => $periodMonth,
                            'allows_partial_payments' => $allowsPartial,
                            'status'                  => 'pending',   // se recalcula en paso 3
                            'metadata'                => ['imported' => true, 'notes' => $notas],
                        ]);
                        $chargeIdByKey[$chargeKey] = $charge->id;
                    } else {
                        $chargeIdByKey[$chargeKey] = 0;
                    }

                    $chargeAmountByKey[$chargeKey] = $montoCargo;
                }

                // Firma del pago: identifica transacciones distintas dentro de un mismo PAGO_REF
                // Caso A: misma firma en todas las filas → 1 pago, múltiples cargos
                // Caso B: firmas distintas           → múltiples pagos, mismo cargo
                $paymentSig = implode('|', [
                    strtoupper(trim($row['METODO_PAGO'] ?? '')),
                    trim($row['FECHA_PAGO'] ?? ''),
                    trim($row['REFERENCIA'] ?? ''),
                ]);

                $parsedRows[$rowNum] = [
                    'charge_key'  => $chargeKey,
                    'charge_id'   => $chargeIdByKey[$chargeKey],
                    'monto_cargo' => $montoCargo,
                    'monto_pagado'=> $montoPagado,
                    'pago_ref'    => $pagoRef,
                    'payment_sig' => $paymentSig,
                    'payment_data' => [
                        'membership_account_id' => $accountId,
                        'club_id'               => $clubId,
                        'payment_method_id'     => $context->paymentMethodId($row['METODO_PAGO'] ?? '')
                                                    ?? $fallbackPaymentMethodId,
                        'paid_at'               => $this->parseDate($row['FECHA_PAGO'] ?? null)
                                                    ?? now()->toDateString(),
                        'reference'             => $row['REFERENCIA'] ?? null,
                        'bank_name'             => $row['BANCO'] ?? null,
                        'check_number'          => $row['NUM_CHEQUE'] ?? null,
                        'notes'                 => $notas,
                        'metadata'              => ['imported' => true],
                    ],
                ];

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        if ($dryRun) {
            $report->record($this->name(), $ok, $errors);
            return;
        }

        // ── Paso 2: procesar pagos ─────────────────────────────────────────────
        $totalAppliedByChargeKey = [];

        // Solo filas que tienen pago registrado
        $rowsWithPayment = array_filter($parsedRows, fn($r) => ($r['monto_pagado'] ?? 0) > 0);

        // Agrupar por PAGO_REF (vacío = sin agrupación)
        $byPagoRef = [];
        foreach ($rowsWithPayment as $rowNum => $r) {
            $byPagoRef[$r['pago_ref']][$rowNum] = $r;
        }

        foreach ($byPagoRef as $pagoRef => $rows) {
            try {
                if (empty($pagoRef)) {
                    // Sin agrupación: 1 pago por fila
                    foreach ($rows as $r) {
                        $this->applyPayment(
                            $r['payment_data'],
                            $r['monto_pagado'],
                            [['charge_id' => $r['charge_id'], 'charge_key' => $r['charge_key'], 'applied_amount' => $r['monto_pagado']]],
                            $totalAppliedByChargeKey
                        );
                    }
                    continue;
                }

                $uniqueChargeKeys  = array_unique(array_column($rows, 'charge_key'));
                $uniquePaymentSigs = array_unique(array_column($rows, 'payment_sig'));

                if (count($uniquePaymentSigs) === 1 && count($uniqueChargeKeys) > 1) {
                    // ── Caso A: 1 firma de pago → múltiples cargos ──────────────
                    // MONTO_PAGADO es el total (redundante en cada fila).
                    // El monto real del pago = suma de MONTO_CARGO de cada cargo único.
                    $seenKeys    = [];
                    $applications = [];
                    $totalAmount  = 0;

                    foreach ($rows as $r) {
                        if (!isset($seenKeys[$r['charge_key']])) {
                            $seenKeys[$r['charge_key']] = true;
                            $amount = $chargeAmountByKey[$r['charge_key']];
                            $applications[] = [
                                'charge_id'      => $r['charge_id'],
                                'charge_key'     => $r['charge_key'],
                                'applied_amount' => $amount,
                            ];
                            $totalAmount += $amount;
                        }
                    }

                    $this->applyPayment(
                        reset($rows)['payment_data'],
                        $totalAmount,
                        $applications,
                        $totalAppliedByChargeKey
                    );
                } else {
                    // ── Caso B (o mixto): múltiples firmas de pago → múltiples pagos ──
                    // Cada firma = 1 pago distinto. MONTO_PAGADO es el monto real de ese pago.
                    $bySig = [];
                    foreach ($rows as $r) {
                        $bySig[$r['payment_sig']][] = $r;
                    }

                    foreach ($bySig as $sigRows) {
                        $seenKeys    = [];
                        $applications = [];
                        $totalAmount  = 0;

                        foreach ($sigRows as $r) {
                            if (!isset($seenKeys[$r['charge_key']])) {
                                $seenKeys[$r['charge_key']] = true;
                                $applications[] = [
                                    'charge_id'      => $r['charge_id'],
                                    'charge_key'     => $r['charge_key'],
                                    'applied_amount' => $r['monto_pagado'],
                                ];
                                $totalAmount += $r['monto_pagado'];
                            }
                        }

                        $this->applyPayment(
                            reset($sigRows)['payment_data'],
                            $totalAmount,
                            $applications,
                            $totalAppliedByChargeKey
                        );
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => 0, 'message' => "PAGO_REF '{$pagoRef}': {$e->getMessage()}"];
            }
        }

        // ── Paso 3: recalcular balance y estatus de cada cargo ────────────────
        foreach ($chargeIdByKey as $chargeKey => $chargeId) {
            try {
                $totalAmount  = $chargeAmountByKey[$chargeKey];
                $totalApplied = $totalAppliedByChargeKey[$chargeKey] ?? 0;
                $balance      = max(0, $totalAmount - $totalApplied);

                $status = match (true) {
                    $balance <= 0                      => 'paid',
                    $totalApplied > 0 && $balance > 0  => 'partial',
                    default                            => 'pending',
                };

                Charge::where('id', $chargeId)->update(['balance' => $balance, 'status' => $status]);
            } catch (\Throwable $e) {
                $errors[] = ['row' => 0, 'message' => "Balance cargo '{$chargeKey}': {$e->getMessage()}"];
            }
        }

        $report->record($this->name(), $ok, $errors);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * Crea un Payment y sus PaymentApplications, y acumula el monto aplicado por cargo.
     *
     * @param array  $paymentData             Datos base del pago
     * @param float  $totalAmount             Monto total del pago
     * @param array  $applications            Lista de ['charge_id', 'charge_key', 'applied_amount']
     * @param array  &$totalAppliedByChargeKey Acumulador de montos aplicados por cargo
     */
    private function applyPayment(
        array  $paymentData,
        float  $totalAmount,
        array  $applications,
        array  &$totalAppliedByChargeKey
    ): void {
        $payment = Payment::create(array_merge($paymentData, ['amount' => $totalAmount]));

        foreach ($applications as $app) {
            PaymentApplication::create([
                'payment_id'     => $payment->id,
                'charge_id'      => $app['charge_id'],
                'applied_amount' => $app['applied_amount'],
            ]);

            $totalAppliedByChargeKey[$app['charge_key']] =
                ($totalAppliedByChargeKey[$app['charge_key']] ?? 0) + $app['applied_amount'];
        }
    }

    private function deriveDueDate(?int $year, ?int $month): ?string
    {
        if (!$year) {
            return null;
        }

        if (!$month) {
            return Carbon::create($year, 1, 31)->toDateString();
        }

        return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
    }
}
