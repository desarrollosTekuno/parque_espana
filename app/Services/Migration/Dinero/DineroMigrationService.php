<?php

namespace App\Services\Migration\Dinero;

use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Memberships\MembershipAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class DineroMigrationService
{
    private const HEADERS = [
        'Cargos' => ['REFERENCIA DEL CARGO', 'NUMERO DE CUENTA', 'CONCEPTO DE COBRO', 'DESCRIPCION', 'IMPORTE', 'FECHA DE EMISION', 'FECHA DE VENCIMIENTO', 'AÑO DEL PERIODO', 'MES DEL PERIODO', 'PAGO EN PARCIALIDADES', 'FOLIO DEL RECIBO', 'IMPORTE PAGADO', 'DESCUENTO', 'CANCELADO', 'FECHA DE CANCELACION', 'MOTIVO DE CANCELACION', 'NOTAS'],
        'Pagos' => ['FOLIO DEL RECIBO', 'NUMERO DE CUENTA', 'CLUB DONDE SE COBRO', 'FECHA DEL PAGO', 'HORA DEL PAGO', 'METODO DE PAGO', 'PARQUE DE LA FORMA DE PAGO', 'IMPORTE', 'REFERENCIA', 'BANCO', 'NUMERO DE CHEQUE', 'SERIE DE CAJA', 'CANCELADO', 'FECHA DE CANCELACION', 'MOTIVO DE CANCELACION', 'NOTAS'],
    ];

    public function review(string $file): array
    {
        $data = $this->read($file);
        return $this->validate($data);
    }

    public function run(string $file, bool $dryRun = false): array
    {
        $data = $this->read($file);
        $report = $this->validate($data);
        if ($report['errors'] !== []) {
            throw new RuntimeException('La plantilla de dinero tiene ' . count($report['errors']) . ' error(es). Revise las filas indicadas en la vista previa.');
        }

        DB::beginTransaction();
        try {
            $this->import($data);
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report['counts'];
    }

    private function read(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly(array_keys(self::HEADERS));
        $book = $reader->load($file);
        try {
            $data = [];
            foreach (self::HEADERS as $name => $headers) {
                if (!$book->sheetNameExists($name)) {
                    throw new RuntimeException("Falta la pestaña {$name}.");
                }
                $data[$name] = $this->rows($book->getSheetByName($name), $headers);
            }
            return $data;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function rows(Worksheet $sheet, array $requiredHeaders): array
    {
        $columns = [];
        foreach ($sheet->getRowIterator(2, 2)->current()->getCellIterator() as $cell) {
            $header = trim((string) $cell->getValue());
            if ($header !== '') {
                $columns[$this->key($header)] = $cell->getColumn();
            }
        }
        foreach ($requiredHeaders as $header) {
            if (!isset($columns[$this->key($header)])) {
                throw new RuntimeException("{$sheet->getTitle()}: falta la columna {$header}.");
            }
        }

        $rows = [];
        for ($number = 4; $number <= $sheet->getHighestDataRow(); $number++) {
            $row = ['_row' => $number];
            foreach ($requiredHeaders as $header) {
                $cell = $sheet->getCell($columns[$this->key($header)] . $number);
                $value = $cell->getValue();
                if (is_numeric($value) && Date::isDateTime($cell)) {
                    $value = Date::excelToDateTimeObject($value)->format(str_contains($header, 'HORA') ? 'H:i' : 'Y-m-d');
                }
                $row[$header] = trim((string) ($value ?? ''));
            }
            if (collect($row)->except('_row')->contains(fn ($value) => $value !== '')) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function validate(array $data): array
    {
        $errors = [];
        $accounts = MembershipAccount::pluck('id', 'membership_number')->all();
        $clubs = Club::all();
        $concepts = ChargeConcept::withTrashed()->get();
        $methods = PaymentMethod::all();
        $charges = [];
        $folios = [];
        $missingAccounts = [];

        if ($accounts === []) {
            return [
                'counts' => [
                    'Cargos' => collect($data['Cargos'])->pluck('REFERENCIA DEL CARGO')->unique()->count(),
                    'Pagos' => count($data['Pagos']),
                    'Recibos' => collect($data['Pagos'])->pluck('FOLIO DEL RECIBO')->unique()->count(),
                ],
                'errors' => [$this->issue('Cargos y Pagos', 4, 'NUMERO DE CUENTA', 'No hay cuentas de socios cargadas. Complete primero la primera fase.')],
            ];
        }

        foreach ($data['Pagos'] as $row) {
            $folio = $row['FOLIO DEL RECIBO'];
            $label = 'Pagos';
            if ($folio === '' || $row['NUMERO DE CUENTA'] === '' || $row['CLUB DONDE SE COBRO'] === '' || $row['FECHA DEL PAGO'] === '' || $row['METODO DE PAGO'] === '' || $row['IMPORTE'] === '') {
                $errors[] = $this->issue($label, $row['_row'], 'DATOS OBLIGATORIOS', 'Faltan folio, cuenta, club, fecha, método o importe.');
            }
            if (!isset($accounts[$row['NUMERO DE CUENTA']]) && !isset($missingAccounts[$row['NUMERO DE CUENTA']])) {
                $errors[] = $this->issue($label, $row['_row'], 'NUMERO DE CUENTA', 'La cuenta no existe. Cargue primero la fase de socios.');
                $missingAccounts[$row['NUMERO DE CUENTA']] = true;
            }
            if (!$clubs->first(fn ($club) => in_array($this->key($row['CLUB DONDE SE COBRO']), [$this->key($club->code), $this->key($club->name)], true))) {
                $errors[] = $this->issue($label, $row['_row'], 'CLUB DONDE SE COBRO', 'El club no existe en el catálogo.');
            }
            if (!$methods->first(fn ($method) => $this->key($method->name) === $this->key($row['METODO DE PAGO']))) {
                $errors[] = $this->issue($label, $row['_row'], 'METODO DE PAGO', 'El método no existe en el catálogo.');
            }
            if (!$this->validDate($row['FECHA DEL PAGO'])) {
                $errors[] = $this->issue($label, $row['_row'], 'FECHA DEL PAGO', 'Capture una fecha válida AAAA-MM-DD.');
            }
            if ($row['HORA DEL PAGO'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $row['HORA DEL PAGO'])) {
                $errors[] = $this->issue($label, $row['_row'], 'HORA DEL PAGO', 'Use HH:MM en formato de 24 horas.');
            }
            if ($this->cents($row['IMPORTE']) === null || (float) $row['IMPORTE'] < 0) {
                $errors[] = $this->issue($label, $row['_row'], 'IMPORTE', 'Capture un importe numérico no negativo.');
            }
            if (!in_array($this->key($row['CANCELADO']), ['', 'SI', 'NO'], true)) {
                $errors[] = $this->issue($label, $row['_row'], 'CANCELADO', 'Escriba SI o NO.');
            }
            if ($this->key($row['CANCELADO']) === 'SI' && (!$this->validDate($row['FECHA DE CANCELACION']) || $row['MOTIVO DE CANCELACION'] === '')) {
                $errors[] = $this->issue($label, $row['_row'], 'CANCELACION', 'Un pago cancelado requiere fecha y motivo.');
            }
            if ($folio !== '') {
                $folios[$folio][] = $row;
            }
        }

        foreach ($data['Cargos'] as $row) {
            $ref = $row['REFERENCIA DEL CARGO'];
            $label = 'Cargos';
            if ($ref === '' || $row['NUMERO DE CUENTA'] === '' || $row['CONCEPTO DE COBRO'] === '' || $row['IMPORTE'] === '') {
                $errors[] = $this->issue($label, $row['_row'], 'DATOS OBLIGATORIOS', 'Faltan referencia, cuenta, concepto o importe.');
            }
            if (!isset($accounts[$row['NUMERO DE CUENTA']]) && !isset($missingAccounts[$row['NUMERO DE CUENTA']])) {
                $errors[] = $this->issue($label, $row['_row'], 'NUMERO DE CUENTA', 'La cuenta no existe. Cargue primero la fase de socios.');
                $missingAccounts[$row['NUMERO DE CUENTA']] = true;
            }
            if (!$concepts->first(fn ($concept) => $this->key($concept->name) === $this->key($row['CONCEPTO DE COBRO']))) {
                $errors[] = $this->issue($label, $row['_row'], 'CONCEPTO DE COBRO', 'El concepto no existe en el catálogo.');
            }
            $amount = $this->cents($row['IMPORTE']);
            if ($amount === null || $amount <= 0) {
                $errors[] = $this->issue($label, $row['_row'], 'IMPORTE', 'Capture un importe mayor que cero.');
            }
            foreach (['FECHA DE EMISION', 'FECHA DE VENCIMIENTO'] as $field) {
                if ($row[$field] !== '' && !$this->validDate($row[$field])) {
                    $errors[] = $this->issue($label, $row['_row'], $field, 'Capture una fecha válida AAAA-MM-DD.');
                }
            }
            if ($row['AÑO DEL PERIODO'] !== '' && (!ctype_digit($row['AÑO DEL PERIODO']) || (int) $row['AÑO DEL PERIODO'] < 1900)) {
                $errors[] = $this->issue($label, $row['_row'], 'AÑO DEL PERIODO', 'Capture un año válido.');
            }
            if ($row['MES DEL PERIODO'] !== '' && (!ctype_digit($row['MES DEL PERIODO']) || (int) $row['MES DEL PERIODO'] < 1 || (int) $row['MES DEL PERIODO'] > 12)) {
                $errors[] = $this->issue($label, $row['_row'], 'MES DEL PERIODO', 'Capture un mes del 1 al 12.');
            }
            if (!in_array($this->key($row['CANCELADO']), ['', 'SI', 'NO'], true)) {
                $errors[] = $this->issue($label, $row['_row'], 'CANCELADO', 'Escriba SI o NO.');
            }
            if ($this->key($row['CANCELADO']) === 'SI' && (!$this->validDate($row['FECHA DE CANCELACION']) || $row['MOTIVO DE CANCELACION'] === '')) {
                $errors[] = $this->issue($label, $row['_row'], 'CANCELACION', 'Un cargo cancelado requiere fecha y motivo.');
            }
            if ($row['FOLIO DEL RECIBO'] !== '') {
                $folio = $row['FOLIO DEL RECIBO'];
                if (!isset($folios[$folio])) {
                    $errors[] = $this->issue($label, $row['_row'], 'FOLIO DEL RECIBO', "No existe el recibo {$folio} en Pagos.");
                } elseif ($folios[$folio][0]['NUMERO DE CUENTA'] !== $row['NUMERO DE CUENTA']) {
                    $errors[] = $this->issue($label, $row['_row'], 'FOLIO DEL RECIBO', "El recibo {$folio} pertenece a otra cuenta.");
                }
                if ($row['IMPORTE PAGADO'] !== '' && $this->cents($row['IMPORTE PAGADO']) === null) {
                    $errors[] = $this->issue($label, $row['_row'], 'IMPORTE PAGADO', 'Capture un importe numérico.');
                }
            } elseif ($row['IMPORTE PAGADO'] !== '' || $row['DESCUENTO'] !== '') {
                $errors[] = $this->issue($label, $row['_row'], 'FOLIO DEL RECIBO', 'Un importe pagado o descuento requiere folio.');
            }
            if ($row['DESCUENTO'] !== '' && ($this->cents($row['DESCUENTO']) === null || (float) $row['DESCUENTO'] < 0)) {
                $errors[] = $this->issue($label, $row['_row'], 'DESCUENTO', 'Capture un descuento no negativo.');
            }
            if ($ref !== '') {
                if (isset($charges[$ref])) {
                    $first = $charges[$ref][0];
                    foreach (['NUMERO DE CUENTA', 'CONCEPTO DE COBRO', 'IMPORTE', 'AÑO DEL PERIODO', 'MES DEL PERIODO'] as $field) {
                        if ($this->key($first[$field]) !== $this->key($row[$field])) {
                            $errors[] = $this->issue($label, $row['_row'], 'REFERENCIA DEL CARGO', "La referencia {$ref} repite un cargo con datos diferentes.");
                            break;
                        }
                    }
                }
                $charges[$ref][] = $row;
            }
        }

        foreach ($folios as $folio => $paymentRows) {
            $account = $paymentRows[0]['NUMERO DE CUENTA'];
            $cancelled = $this->key($paymentRows[0]['CANCELADO']) === 'SI';
            $cash = 0;
            foreach ($paymentRows as $row) {
                if ($row['NUMERO DE CUENTA'] !== $account || ($this->key($row['CANCELADO']) === 'SI') !== $cancelled) {
                    $errors[] = $this->issue('Pagos', $row['_row'], 'FOLIO DEL RECIBO', "Las filas del recibo {$folio} deben tener la misma cuenta y estado de cancelación.");
                }
                $cash += $this->cents($row['IMPORTE']) ?? 0;
            }
            $applied = 0;
            foreach ($data['Cargos'] as $row) {
                if ($row['FOLIO DEL RECIBO'] === $folio) {
                    $amount = $this->cents($row['IMPORTE']) ?? 0;
                    $discount = $this->cents($row['DESCUENTO']) ?? 0;
                    $applied += $row['IMPORTE PAGADO'] === '' ? $amount - $discount : ($this->cents($row['IMPORTE PAGADO']) ?? 0);
                }
            }
            if ($cash !== $applied) {
                $errors[] = $this->issue('Pagos', $paymentRows[0]['_row'], 'IMPORTE', "El recibo {$folio} suma " . $this->money($cash) . ' y los cargos aplican ' . $this->money($applied) . '.');
            }
        }

        foreach ($charges as $ref => $rows) {
            $amount = $this->cents($rows[0]['IMPORTE']) ?? 0;
            $used = 0;
            foreach ($rows as $row) {
                if ($row['FOLIO DEL RECIBO'] !== '' && isset($folios[$row['FOLIO DEL RECIBO']]) && $this->key($folios[$row['FOLIO DEL RECIBO']][0]['CANCELADO']) !== 'SI') {
                    $discount = $this->cents($row['DESCUENTO']) ?? 0;
                    $used += ($row['IMPORTE PAGADO'] === '' ? $amount - $discount : ($this->cents($row['IMPORTE PAGADO']) ?? 0)) + $discount;
                }
            }
            if ($used > $amount) {
                $errors[] = $this->issue('Cargos', $rows[0]['_row'], 'IMPORTE PAGADO', "El cargo {$ref} queda sobrepagado.");
            }
            if ($this->key($rows[0]['CANCELADO']) === 'SI' && $used > 0) {
                $errors[] = $this->issue('Cargos', $rows[0]['_row'], 'CANCELADO', "El cargo {$ref} está cancelado y tiene pagos vigentes.");
            }
        }

        return [
            'counts' => ['Cargos' => count($charges), 'Pagos' => count($data['Pagos']), 'Recibos' => count($folios)],
            'errors' => $errors,
        ];
    }

    private function import(array $data): void
    {
        $accounts = MembershipAccount::with(['memberships', 'primaryHolder'])->get()->keyBy('membership_number');
        $clubs = Club::all();
        $concepts = ChargeConcept::withTrashed()->get();
        $methods = PaymentMethod::all();
        $cashiers = User::whereNotNull('code')->get()->keyBy(fn ($user) => $this->key($user->code));
        $chargeRows = collect($data['Cargos'])->groupBy('REFERENCIA DEL CARGO');
        $paymentRows = collect($data['Pagos'])->groupBy('FOLIO DEL RECIBO');
        $existingCharges = Charge::where('metadata->migration_source', 'cliente')->get()->keyBy('metadata.migration_reference');
        $existingPayments = Payment::where('metadata->migration_source', 'cliente')->get()
            ->keyBy(fn ($payment) => ($payment->metadata['migration_folio'] ?? '') . '|' . ($payment->metadata['migration_line'] ?? ''));
        $savedCharges = [];
        $savedPayments = [];

        foreach ($chargeRows as $ref => $rows) {
            $row = $rows->first();
            $account = $accounts[$row['NUMERO DE CUENTA']];
            $concept = $concepts->first(fn ($item) => $this->key($item->name) === $this->key($row['CONCEPTO DE COBRO']));
            $charge = $existingCharges[$ref] ?? new Charge();
            $charge->membership_account_id = $account->id;
            $charge->membership_id = $account->memberships->first()?->id;
            $charge->member_id = $account->primaryHolder?->member_id;
            $charge->concept_id = $concept->id;
            $charge->description = $row['DESCRIPCION'] ?: null;
            $charge->amount = $this->money($this->cents($row['IMPORTE']));
            $charge->balance = $charge->amount;
            $charge->issue_date = $row['FECHA DE EMISION'] ?: null;
            $charge->due_date = $row['FECHA DE VENCIMIENTO'] ?: null;
            $charge->period_year = $row['AÑO DEL PERIODO'] !== '' ? (int) $row['AÑO DEL PERIODO'] : null;
            $charge->period_month = $row['MES DEL PERIODO'] !== '' ? (int) $row['MES DEL PERIODO'] : null;
            $charge->allows_partial_payments = $this->key($row['PAGO EN PARCIALIDADES']) === 'SI';
            $charge->status = 'pending';
            $charge->cancelled_at = $this->key($row['CANCELADO']) === 'SI' ? $row['FECHA DE CANCELACION'] : null;
            $charge->cancellation_reason = $this->key($row['CANCELADO']) === 'SI' ? $row['MOTIVO DE CANCELACION'] : null;
            $charge->metadata = ['migration_source' => 'cliente', 'migration_reference' => $ref, 'migration_notes' => $row['NOTAS'] ?: null];
            $charge->save();
            $savedCharges[$ref] = $charge;
        }

        foreach ($paymentRows as $folio => $rows) {
            $groupId = ($existingPayments[$folio . '|1'] ?? null)?->payment_group_id ?: (string) Str::uuid();
            foreach ($rows->values() as $index => $row) {
                $line = $index + 1;
                $account = $accounts[$row['NUMERO DE CUENTA']];
                $club = $clubs->first(fn ($item) => in_array($this->key($row['CLUB DONDE SE COBRO']), [$this->key($item->code), $this->key($item->name)], true));
                $method = $methods->first(fn ($item) => $this->key($item->name) === $this->key($row['METODO DE PAGO']));
                $payment = $existingPayments[$folio . '|' . $line] ?? new Payment();
                $payment->payment_group_id = $groupId;
                $payment->membership_account_id = $account->id;
                $payment->club_id = $club->id;
                $payment->payment_method_id = $method->id;
                $payment->amount = $this->money($this->cents($row['IMPORTE']));
                $payment->paid_at = $row['FECHA DEL PAGO'] . ' ' . ($row['HORA DEL PAGO'] ?: '00:00') . ':00';
                $payment->reference = $row['REFERENCIA'] ?: null;
                $payment->bank_name = $row['BANCO'] ?: null;
                $payment->check_number = $row['NUMERO DE CHEQUE'] ?: null;
                $payment->notes = $row['NOTAS'] ?: null;
                $payment->received_by = ($cashiers[$this->key($row['SERIE DE CAJA'])] ?? null)?->id;
                $payment->status = $this->key($row['CANCELADO']) === 'SI' ? 'cancelled' : 'registered';
                $payment->cancelled_at = $payment->status === 'cancelled' ? $row['FECHA DE CANCELACION'] : null;
                $payment->cancellation_reason = $payment->status === 'cancelled' ? $row['MOTIVO DE CANCELACION'] : null;
                $payment->folio = $line === 1 ? $folio : null;
                $payment->metadata = [
                    'migration_source' => 'cliente',
                    'migration_folio' => $folio,
                    'migration_line' => $line,
                    'migration_row' => $row['_row'],
                    'cashier_series' => $row['SERIE DE CAJA'] ?: null,
                    'payment_method_club' => $row['PARQUE DE LA FORMA DE PAGO'] ?: null,
                    'settlement_channel' => 'legacy',
                    'affects_cash_cut' => false,
                ];
                $payment->save();
                PaymentApplication::where('payment_id', $payment->id)->delete();
                $savedPayments[$folio][] = $payment;
            }
        }

        foreach ($paymentRows as $folio => $rows) {
            $payments = $savedPayments[$folio];
            $remaining = array_map(fn ($payment) => $this->cents($payment->amount), $payments);
            $lines = collect($data['Cargos'])->filter(fn ($row) => $row['FOLIO DEL RECIBO'] === $folio)->groupBy('REFERENCIA DEL CARGO');
            foreach ($lines as $ref => $chargeApplications) {
                $amount = $this->cents($chargeApplications->first()['IMPORTE']);
                $cash = 0;
                $discount = 0;
                foreach ($chargeApplications as $row) {
                    $rowDiscount = $this->cents($row['DESCUENTO']) ?? 0;
                    $cash += $row['IMPORTE PAGADO'] === '' ? $amount - $rowDiscount : $this->cents($row['IMPORTE PAGADO']);
                    $discount += $rowDiscount;
                }
                $allocations = [];
                foreach ($payments as $index => $payment) {
                    $part = min($cash, $remaining[$index]);
                    if ($part > 0) {
                        $allocations[$index] = $part;
                        $remaining[$index] -= $part;
                        $cash -= $part;
                    }
                }
                $discountIndex = $allocations !== [] ? array_key_last($allocations) : 0;
                foreach ($allocations as $index => $part) {
                    PaymentApplication::create([
                        'payment_id' => $payments[$index]->id,
                        'charge_id' => $savedCharges[$ref]->id,
                        'applied_amount' => $this->money($part),
                        'discount' => $index === $discountIndex && $discount > 0 ? $this->money($discount) : null,
                    ]);
                }
                if ($allocations === [] && $discount > 0) {
                    PaymentApplication::create([
                        'payment_id' => $payments[0]->id,
                        'charge_id' => $savedCharges[$ref]->id,
                        'applied_amount' => '0.00',
                        'discount' => $this->money($discount),
                    ]);
                }
            }
        }

        foreach ($chargeRows as $ref => $rows) {
            $charge = $savedCharges[$ref];
            if ($charge->cancelled_at) {
                $charge->balance = '0.00';
                $charge->status = 'cancelled';
            } else {
                $used = 0;
                foreach ($rows as $row) {
                    $folio = $row['FOLIO DEL RECIBO'];
                    if ($folio !== '' && $savedPayments[$folio][0]->status !== 'cancelled') {
                        $discount = $this->cents($row['DESCUENTO']) ?? 0;
                        $used += ($row['IMPORTE PAGADO'] === '' ? $this->cents($row['IMPORTE']) - $discount : $this->cents($row['IMPORTE PAGADO'])) + $discount;
                    }
                }
                $balance = $this->cents($charge->amount) - $used;
                $charge->balance = $this->money($balance);
                $charge->status = $balance === 0 ? 'paid' : ($used > 0 ? 'partial' : 'pending');
            }
            $charge->save();
        }
    }

    private function issue(string $sheet, int $row, string $field, string $message): array
    {
        return compact('sheet', 'row', 'field', 'message');
    }

    private function key(?string $value): string
    {
        return mb_strtoupper(trim(Str::ascii($value ?? '')));
    }

    private function cents(?string $value): ?int
    {
        return $value !== null && $value !== '' && is_numeric($value) ? (int) round((float) $value * 100) : null;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function validDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
