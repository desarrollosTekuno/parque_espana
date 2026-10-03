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
use App\Services\Billing\MembershipChargeService;
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

    /**
     * Reglas de Cargos y Pagos. Bloquea todo lo que dejaria saldos, cobros o mensualidades
     * inconsistentes; lo que solo es informativo (referencia de una tarjeta, banco...) queda como aviso.
     */
    private function validate(array $data): array
    {
        $errors = [];
        $warnings = [];
        $err = function (string $sheet, ?int $row, string $field, string $message, string $origin) use (&$errors) {
            $errors[] = $this->issue($sheet, $row, $field, $message, $origin);
        };
        $warn = function (string $sheet, ?int $row, string $field, string $message) use (&$warnings) {
            $warnings[] = compact('sheet', 'row', 'field', 'message');
        };

        $accounts = MembershipAccount::pluck('id', 'membership_number')->all();
        $clubs = Club::all();
        $concepts = ChargeConcept::withTrashed()->get();
        $methods = PaymentMethod::all();
        $cashierCodes = User::whereNotNull('code')->pluck('code')->map(fn ($code) => $this->key($code))->flip();
        $today = now()->toDateString();
        $charges = [];
        $folios = [];
        $missingAccounts = [];
        $findClub = fn (string $value) => $clubs->first(fn ($club) => in_array($this->key($value), [$this->key($club->code), $this->key($club->name)], true));
        $findConcept = fn (string $value) => $concepts->first(fn ($concept) => $this->key($concept->name) === $this->key($value));

        $counts = [
            'Cargos' => collect($data['Cargos'])->pluck('REFERENCIA DEL CARGO')->filter()->unique()->count(),
            'Pagos' => count($data['Pagos']),
            'Recibos' => collect($data['Pagos'])->pluck('FOLIO DEL RECIBO')->filter()->unique()->count(),
        ];
        if ($accounts === []) {
            return [
                'counts' => $counts,
                'errors' => [$this->issue('Cargos y Pagos', null, 'NUMERO DE CUENTA', 'No hay cuentas de socios cargadas. Complete primero la primera fase.', 'Falta la primera fase')],
                'warnings' => [],
            ];
        }

        // ───────────── Pagos ─────────────
        foreach ($data['Pagos'] as $row) {
            $n = $row['_row'];
            $folio = $row['FOLIO DEL RECIBO'];
            foreach (['FOLIO DEL RECIBO', 'NUMERO DE CUENTA', 'CLUB DONDE SE COBRO', 'FECHA DEL PAGO', 'METODO DE PAGO', 'IMPORTE'] as $field) {
                if ($row[$field] === '') {
                    $err('Pagos', $n, $field, 'Campo obligatorio vacio.', 'Falta dato');
                }
            }
            if ($row['NUMERO DE CUENTA'] !== '' && !isset($accounts[$row['NUMERO DE CUENTA']])) {
                $err('Pagos', $n, 'NUMERO DE CUENTA', "{$row['NUMERO DE CUENTA']} no existe. Cargue primero la fase de socios o revise el numero.", 'No existe en el sistema');
            }
            if ($row['CLUB DONDE SE COBRO'] !== '' && !$findClub($row['CLUB DONDE SE COBRO'])) {
                $err('Pagos', $n, 'CLUB DONDE SE COBRO', "El club \"{$row['CLUB DONDE SE COBRO']}\" no existe.", 'No existe en el sistema');
            }
            if ($row['PARQUE DE LA FORMA DE PAGO'] !== '' && !$findClub($row['PARQUE DE LA FORMA DE PAGO'])) {
                $err('Pagos', $n, 'PARQUE DE LA FORMA DE PAGO', "El club \"{$row['PARQUE DE LA FORMA DE PAGO']}\" no existe.", 'No existe en el sistema');
            }
            $method = $row['METODO DE PAGO'] !== '' ? $methods->first(fn ($m) => $this->key($m->name) === $this->key($row['METODO DE PAGO'])) : null;
            if ($row['METODO DE PAGO'] !== '' && !$method) {
                $err('Pagos', $n, 'METODO DE PAGO', "\"{$row['METODO DE PAGO']}\" no existe en el catalogo.", 'No existe en el sistema');
            }
            if ($method?->requires_check_number && $row['NUMERO DE CHEQUE'] === '') {
                $warn('Pagos', $n, 'NUMERO DE CHEQUE', "El metodo {$method->name} lleva numero de cheque y esta vacio.");
            }
            if ($method?->requires_reference && $row['REFERENCIA'] === '') {
                $warn('Pagos', $n, 'REFERENCIA', "El metodo {$method->name} lleva referencia y esta vacia.");
            }
            if ($method?->requires_bank_name && $row['BANCO'] === '') {
                $warn('Pagos', $n, 'BANCO', "El metodo {$method->name} lleva banco y esta vacio.");
            }
            if ($row['FECHA DEL PAGO'] !== '') {
                if (!$this->validDate($row['FECHA DEL PAGO'])) {
                    $err('Pagos', $n, 'FECHA DEL PAGO', "\"{$row['FECHA DEL PAGO']}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
                } elseif ($row['FECHA DEL PAGO'] > $today) {
                    $err('Pagos', $n, 'FECHA DEL PAGO', 'Es una fecha futura.', 'Dato contradictorio');
                }
            }
            if ($row['HORA DEL PAGO'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $row['HORA DEL PAGO'])) {
                $err('Pagos', $n, 'HORA DEL PAGO', 'Use HH:MM en formato de 24 horas.', 'Dato invalido');
            }
            if ($row['IMPORTE'] !== '' && ($this->cents($row['IMPORTE']) === null || $this->cents($row['IMPORTE']) <= 0)) {
                $err('Pagos', $n, 'IMPORTE', "\"{$row['IMPORTE']}\" no es valido; debe ser mayor que cero.", 'Dato invalido');
            }
            if ($row['SERIE DE CAJA'] !== '' && !isset($cashierCodes[$this->key($row['SERIE DE CAJA'])])) {
                $err('Pagos', $n, 'SERIE DE CAJA', "La serie {$row['SERIE DE CAJA']} no existe; debe venir en Personal. Sin ella el cobro no se liga a su cajero.", 'No existe en el sistema');
            }
            $this->checkCancellation('Pagos', $row, $err, 'pago');
            if ($folio !== '') {
                $folios[$folio][] = $row;
            }
        }

        // Folios que ya usa un cobro hecho en el sistema (el folio no se puede repetir)
        $taken = Payment::whereIn('folio', array_map('strval', array_keys($folios)))
            ->where(fn ($q) => $q->whereNull('metadata->migration_source')->orWhere('metadata->migration_source', '!=', 'cliente'))
            ->pluck('folio')->flip();

        foreach ($folios as $folio => $paymentRows) {
            $first = $paymentRows[0];
            if (isset($taken[$folio])) {
                $err('Pagos', $first['_row'], 'FOLIO DEL RECIBO', "El folio {$folio} ya lo tiene un cobro registrado en el sistema.", 'Repetido');
            }
            foreach (array_slice($paymentRows, 1) as $row) {
                foreach (['NUMERO DE CUENTA', 'CLUB DONDE SE COBRO', 'FECHA DEL PAGO'] as $field) {
                    if ($this->key($row[$field]) !== $this->key($first[$field])) {
                        $err('Pagos', $row['_row'], $field, "Las filas del recibo {$folio} deben tener el mismo dato que la fila {$first['_row']} ({$first[$field]}).", 'Dato contradictorio');
                    }
                }
                if (($this->key($row['CANCELADO']) === 'SI') !== ($this->key($first['CANCELADO']) === 'SI')) {
                    $err('Pagos', $row['_row'], 'CANCELADO', "Un recibo se cancela completo: todas las filas del recibo {$folio} deben decir lo mismo que la fila {$first['_row']}.", 'Dato contradictorio');
                }
            }
        }

        // ───────────── Cargos ─────────────
        $monthlyPeriods = [];
        foreach ($data['Cargos'] as $row) {
            $n = $row['_row'];
            $ref = $row['REFERENCIA DEL CARGO'];
            foreach (['REFERENCIA DEL CARGO', 'NUMERO DE CUENTA', 'CONCEPTO DE COBRO', 'IMPORTE'] as $field) {
                if ($row[$field] === '') {
                    $err('Cargos', $n, $field, 'Campo obligatorio vacio.', 'Falta dato');
                }
            }
            if ($row['NUMERO DE CUENTA'] !== '' && !isset($accounts[$row['NUMERO DE CUENTA']]) && !isset($missingAccounts[$row['NUMERO DE CUENTA']])) {
                $err('Cargos', $n, 'NUMERO DE CUENTA', "{$row['NUMERO DE CUENTA']} no existe. Cargue primero la fase de socios o revise el numero.", 'No existe en el sistema');
                $missingAccounts[$row['NUMERO DE CUENTA']] = true;
            }
            $concept = $row['CONCEPTO DE COBRO'] !== '' ? $findConcept($row['CONCEPTO DE COBRO']) : null;
            if ($row['CONCEPTO DE COBRO'] !== '' && !$concept) {
                $err('Cargos', $n, 'CONCEPTO DE COBRO', "\"{$row['CONCEPTO DE COBRO']}\" no existe en el catalogo.", 'No existe en el sistema');
            }
            $amount = $this->cents($row['IMPORTE']);
            if ($row['IMPORTE'] !== '' && ($amount === null || $amount <= 0)) {
                $err('Cargos', $n, 'IMPORTE', "\"{$row['IMPORTE']}\" no es valido; debe ser mayor que cero.", 'Dato invalido');
            }

            // Periodo: la mensualidad lo necesita para saber que mes cubre (y no volver a cobrarlo)
            $isMonthly = $concept && in_array($concept->code, MembershipChargeService::MONTHLY_FEE_FAMILY_CODES, true);
            $year = $row['AÑO DEL PERIODO'];
            $month = $row['MES DEL PERIODO'];
            if ($year !== '' && (!ctype_digit($year) || (int) $year < 1900 || (int) $year > 2100)) {
                $err('Cargos', $n, 'AÑO DEL PERIODO', "\"{$year}\" no es un año valido.", 'Dato invalido');
            }
            if ($month !== '' && (!ctype_digit($month) || (int) $month < 1 || (int) $month > 12)) {
                $err('Cargos', $n, 'MES DEL PERIODO', "\"{$month}\" no es un mes del 1 al 12.", 'Dato invalido');
            }
            if ($isMonthly) {
                if ($year === '') {
                    $err('Cargos', $n, 'AÑO DEL PERIODO', "{$concept->name} es una mensualidad y el año esta vacio. Sin el periodo el sistema no sabe que mes cubre y lo volveria a cobrar.", 'Falta dato');
                }
                if ($month === '') {
                    $err('Cargos', $n, 'MES DEL PERIODO', "{$concept->name} es una mensualidad y el mes esta vacio. Sin el periodo el sistema no sabe que mes cubre y lo volveria a cobrar.", 'Falta dato');
                }
            } elseif (($year === '') !== ($month === '')) {
                $err('Cargos', $n, $year === '' ? 'AÑO DEL PERIODO' : 'MES DEL PERIODO', 'El periodo lleva año y mes juntos; uno de los dos esta vacio.', 'Falta dato');
            }

            // Fechas
            foreach (['FECHA DE EMISION', 'FECHA DE VENCIMIENTO'] as $field) {
                if ($row[$field] !== '' && !$this->validDate($row[$field])) {
                    $err('Cargos', $n, $field, "\"{$row[$field]}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
                }
            }
            if ($this->validDate($row['FECHA DE EMISION']) && $this->validDate($row['FECHA DE VENCIMIENTO']) && $row['FECHA DE VENCIMIENTO'] < $row['FECHA DE EMISION']) {
                $err('Cargos', $n, 'FECHA DE VENCIMIENTO', 'Es anterior a la FECHA DE EMISION.', 'Dato contradictorio');
            }
            if (!$isMonthly && $concept && $row['FECHA DE EMISION'] === '' && $row['FOLIO DEL RECIBO'] === '') {
                $err('Cargos', $n, 'FECHA DE EMISION', 'Vacia y el cargo no tiene pago. Sin fecha no se sabe desde cuando se debe (vencidos, reportes).', 'Falta dato');
            }

            if (!in_array($this->key($row['PAGO EN PARCIALIDADES']), ['', 'SI', 'NO'], true)) {
                $err('Cargos', $n, 'PAGO EN PARCIALIDADES', 'Escriba SI o NO.', 'Dato invalido');
            }
            $this->checkCancellation('Cargos', $row, $err, 'cargo');

            // Lo pagado
            if ($row['FOLIO DEL RECIBO'] !== '') {
                $folio = $row['FOLIO DEL RECIBO'];
                if (!isset($folios[$folio])) {
                    $err('Cargos', $n, 'FOLIO DEL RECIBO', "No existe el recibo {$folio} en Pagos.", 'No existe en la plantilla');
                } elseif ($folios[$folio][0]['NUMERO DE CUENTA'] !== $row['NUMERO DE CUENTA']) {
                    $err('Cargos', $n, 'FOLIO DEL RECIBO', "El recibo {$folio} es de la cuenta {$folios[$folio][0]['NUMERO DE CUENTA']}, no de {$row['NUMERO DE CUENTA']}.", 'Dato contradictorio');
                }
                if ($row['IMPORTE PAGADO'] !== '' && ($this->cents($row['IMPORTE PAGADO']) === null || $this->cents($row['IMPORTE PAGADO']) < 0)) {
                    $err('Cargos', $n, 'IMPORTE PAGADO', "\"{$row['IMPORTE PAGADO']}\" no es un importe valido.", 'Dato invalido');
                } elseif ($row['IMPORTE PAGADO'] !== '' && $this->cents($row['IMPORTE PAGADO']) === 0 && ($this->cents($row['DESCUENTO']) ?? 0) === 0) {
                    $err('Cargos', $n, 'IMPORTE PAGADO', 'Esta en 0 y no hay descuento: el recibo no le aplica nada a este cargo. Capture lo pagado o el descuento, o quite el folio.', 'Dato contradictorio');
                }
            } else {
                if ($row['IMPORTE PAGADO'] !== '') {
                    $err('Cargos', $n, 'IMPORTE PAGADO', 'Tiene importe pagado y FOLIO DEL RECIBO esta vacio. Capture el folio del recibo con que se pago.', 'Dato contradictorio');
                }
                if ($row['DESCUENTO'] !== '') {
                    $err('Cargos', $n, 'DESCUENTO', 'Tiene descuento y FOLIO DEL RECIBO esta vacio. El descuento se aplica al pagar; capture el folio.', 'Dato contradictorio');
                }
            }
            if ($row['DESCUENTO'] !== '') {
                $discount = $this->cents($row['DESCUENTO']);
                if ($discount === null || $discount < 0) {
                    $err('Cargos', $n, 'DESCUENTO', "\"{$row['DESCUENTO']}\" no es un importe valido.", 'Dato invalido');
                } elseif ($amount !== null && $discount > $amount) {
                    $err('Cargos', $n, 'DESCUENTO', 'Es mayor que el IMPORTE del cargo.', 'Dato contradictorio');
                }
            }

            // Misma referencia = mismo cargo pagado en varios recibos
            if ($ref !== '') {
                if (isset($charges[$ref])) {
                    $first = $charges[$ref][0];
                    foreach (['NUMERO DE CUENTA', 'CONCEPTO DE COBRO', 'IMPORTE', 'AÑO DEL PERIODO', 'MES DEL PERIODO', 'CANCELADO'] as $field) {
                        if ($this->key($first[$field]) !== $this->key($row[$field])) {
                            $err('Cargos', $n, $field, "La referencia {$ref} ya esta en la fila {$first['_row']} con otro valor en {$field}. Si es el mismo cargo pagado en varios recibos, los datos del cargo deben ser iguales; si es otro cargo, use otra referencia.", 'Dato contradictorio');
                            break;
                        }
                    }
                } elseif ($isMonthly && $year !== '' && $month !== '' && $this->key($row['CANCELADO']) !== 'SI') {
                    // Dos mensualidades vigentes del mismo mes en la misma cuenta = cobro doble
                    $period = "{$row['NUMERO DE CUENTA']}|{$year}-{$month}";
                    if (isset($monthlyPeriods[$period])) {
                        $err('Cargos', $n, 'MES DEL PERIODO', "La cuenta ya tiene la mensualidad de {$month}/{$year} en la fila {$monthlyPeriods[$period]}. Si es el mismo cargo use la misma referencia; si una se cancelo, marque CANCELADO = SI.", 'Repetido');
                    }
                    $monthlyPeriods[$period] ??= $n;
                }
                $charges[$ref][] = $row;
            }
        }

        // ───────────── Cuadre de cada recibo ─────────────
        $byFolio = collect($data['Cargos'])->filter(fn ($row) => $row['FOLIO DEL RECIBO'] !== '')->groupBy('FOLIO DEL RECIBO');
        foreach ($folios as $folio => $paymentRows) {
            $cash = array_sum(array_map(fn ($row) => $this->cents($row['IMPORTE']) ?? 0, $paymentRows));
            $applied = 0;
            foreach ($byFolio->get($folio, collect()) as $row) {
                $amount = $this->cents($row['IMPORTE']) ?? 0;
                $discount = $this->cents($row['DESCUENTO']) ?? 0;
                $applied += $row['IMPORTE PAGADO'] === '' ? $amount - $discount : ($this->cents($row['IMPORTE PAGADO']) ?? 0);
            }
            if (!$byFolio->has($folio)) {
                $err('Pagos', $paymentRows[0]['_row'], 'FOLIO DEL RECIBO', "Ningun cargo de la pestaña Cargos tiene el folio {$folio}: no se sabe que pago.", 'Falta dato');
            } elseif ($cash !== $applied) {
                $err('Pagos', $paymentRows[0]['_row'], 'IMPORTE', "El recibo {$folio} suma " . $this->money($cash) . ' en Pagos y los cargos con ese folio suman ' . $this->money($applied) . ' (IMPORTE PAGADO, o IMPORTE - DESCUENTO). Deben ser iguales.', 'Dato contradictorio');
            }
        }

        // ───────────── Saldo de cada cargo ─────────────
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
                $err('Cargos', $rows[0]['_row'], 'IMPORTE PAGADO', "El cargo {$ref} queda sobrepagado: se le aplican " . $this->money($used) . ' y el cargo es de ' . $this->money($amount) . '.', 'Dato contradictorio');
            }
            if ($this->key($rows[0]['CANCELADO']) === 'SI' && $used > 0) {
                $err('Cargos', $rows[0]['_row'], 'CANCELADO', "El cargo {$ref} esta cancelado y tiene pagos vigentes. Cancele tambien el recibo o quite el folio.", 'Dato contradictorio');
            }
            if ($used > 0 && $used < $amount && $this->key($rows[0]['PAGO EN PARCIALIDADES']) !== 'SI' && $this->key($rows[0]['CANCELADO']) !== 'SI') {
                $warn('Cargos', $rows[0]['_row'], 'PAGO EN PARCIALIDADES', "El cargo {$ref} queda con saldo de " . $this->money($amount - $used) . ' y no dice SI; en caja se tendra que pagar completo.');
            }
        }

        return ['counts' => $counts, 'errors' => $errors, 'warnings' => $warnings];
    }

    /** CANCELADO = SI pide fecha y motivo; si no esta cancelado, esos campos no se llenan. */
    private function checkCancellation(string $sheet, array $row, callable $err, string $what): void
    {
        $cancelled = $this->key($row['CANCELADO']);
        if (!in_array($cancelled, ['', 'SI', 'NO'], true)) {
            $err($sheet, $row['_row'], 'CANCELADO', 'Escriba SI o NO.', 'Dato invalido');
            return;
        }
        if ($cancelled === 'SI') {
            if ($row['FECHA DE CANCELACION'] === '') {
                $err($sheet, $row['_row'], 'FECHA DE CANCELACION', "CANCELADO dice SI y la fecha esta vacia.", 'Falta dato');
            } elseif (!$this->validDate($row['FECHA DE CANCELACION'])) {
                $err($sheet, $row['_row'], 'FECHA DE CANCELACION', "\"{$row['FECHA DE CANCELACION']}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
            }
            if ($row['MOTIVO DE CANCELACION'] === '') {
                $err($sheet, $row['_row'], 'MOTIVO DE CANCELACION', "CANCELADO dice SI y el motivo esta vacio.", 'Falta dato');
            }
            return;
        }
        foreach (['FECHA DE CANCELACION', 'MOTIVO DE CANCELACION'] as $field) {
            if ($row[$field] !== '') {
                $err($sheet, $row['_row'], $field, "El {$what} no esta cancelado (CANCELADO vacio o NO) y este campo tiene dato. Ponga CANCELADO = SI o borre el dato.", 'Dato contradictorio');
            }
        }
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

        // Fecha del primer pago de cada recibo (para cargos sueltos sin fecha de emision)
        $paymentDates = collect($data['Pagos'])->groupBy('FOLIO DEL RECIBO')
            ->map(fn ($rows) => $rows->pluck('FECHA DEL PAGO')->filter()->min());

        foreach ($chargeRows as $ref => $rows) {
            $row = $rows->first();
            $account = $accounts[$row['NUMERO DE CUENTA']];
            $concept = $concepts->first(fn ($item) => $this->key($item->name) === $this->key($row['CONCEPTO DE COBRO']));
            $isMonthly = in_array($concept->code, MembershipChargeService::MONTHLY_FEE_FAMILY_CODES, true)
                && $row['AÑO DEL PERIODO'] !== '' && $row['MES DEL PERIODO'] !== '';
            $period = $isMonthly ? Carbon::create((int) $row['AÑO DEL PERIODO'], (int) $row['MES DEL PERIODO'], 1) : null;
            $firstPayment = $rows->pluck('FOLIO DEL RECIBO')->filter()->map(fn ($folio) => $paymentDates[$folio] ?? null)->filter()->min();
            $charge = $existingCharges[$ref] ?? new Charge();
            $charge->membership_account_id = $account->id;
            $charge->membership_id = $account->memberships->first()?->id;
            $charge->member_id = $account->primaryHolder?->member_id;
            $charge->concept_id = $concept->id;
            $charge->description = $row['DESCRIPCION'] ?: null;
            $charge->amount = $this->money($this->cents($row['IMPORTE']));
            $charge->balance = $charge->amount;
            // Igual que MembershipChargeService::createRecurringMonthlyCharge: la mensualidad se
            // emite el dia 1 del periodo y vence el dia 10. Sin vencimiento, el bloqueo por adeudo
            // (MembershipDelinquencyService) y los vencidos de Cobranza no la cuentan.
            $charge->issue_date = $row['FECHA DE EMISION'] ?: ($period?->toDateString() ?? $firstPayment);
            $charge->due_date = $row['FECHA DE VENCIMIENTO']
                ?: ($period ? $period->copy()->day(min(10, $period->daysInMonth))->toDateString() : null);
            $charge->period_year = $row['AÑO DEL PERIODO'] !== '' ? (int) $row['AÑO DEL PERIODO'] : null;
            $charge->period_month = $row['MES DEL PERIODO'] !== '' ? (int) $row['MES DEL PERIODO'] : null;
            $charge->allows_partial_payments = $this->key($row['PAGO EN PARCIALIDADES']) === 'SI';
            $charge->status = 'pending';
            $charge->cancelled_at = $this->key($row['CANCELADO']) === 'SI' ? $row['FECHA DE CANCELACION'] : null;
            $charge->cancellation_reason = $this->key($row['CANCELADO']) === 'SI' ? $row['MOTIVO DE CANCELACION'] : null;
            $charge->metadata = array_merge([
                'migration_source' => 'cliente',
                'migration_reference' => $ref,
                'migration_notes' => $row['NOTAS'] ?: null,
                'concept_code' => $concept->code,
                'generation_type' => 'migration',
            ], $isMonthly ? [
                'target_monthly_fee' => (float) $charge->amount,
                'monthly_fee_share' => (float) $charge->amount,
                'effective_monthly_fee' => (float) $charge->amount,
            ] : []);
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

        $this->setBackfillFloors($savedCharges);
    }

    /**
     * Piso de mensualidades (memberships.accounts.billing_backfill_floor), el mismo que usa el
     * sistema al condonar adeudo: el mes siguiente a la ultima mensualidad cargada del grupo.
     * Asi, al buscar al socio en Cobranza (MembershipChargeService::ensureMonthlyChargesUpToToday)
     * el sistema no vuelve a crear meses que la plantilla ya cubre: ni un mes condonado
     * (cancelado), ni un mes que el cliente no mando porque ya estaba pagado. A partir del piso
     * el sistema genera las mensualidades normalmente.
     *
     * @param array<string, Charge> $charges
     */
    private function setBackfillFloors(array $charges): void
    {
        $monthlyConceptIds = ChargeConcept::withTrashed()
            ->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)
            ->pluck('id')
            ->all();
        $latestByAccount = [];
        foreach ($charges as $charge) {
            if (!in_array($charge->concept_id, $monthlyConceptIds) || !$charge->period_year || !$charge->period_month) {
                continue;
            }
            $period = sprintf('%04d-%02d', $charge->period_year, $charge->period_month);
            $latestByAccount[$charge->membership_account_id] = max($latestByAccount[$charge->membership_account_id] ?? '', $period);
        }

        $groups = [];
        foreach (MembershipAccount::whereIn('id', array_keys($latestByAccount))->get() as $account) {
            $key = $account->account_group_id ? 'g' . $account->account_group_id : 'a' . $account->id;
            $groups[$key]['latest'] = max($groups[$key]['latest'] ?? '', $latestByAccount[$account->id]);
            $groups[$key]['group'] = $account->account_group_id;
            $groups[$key]['account'] = $account->id;
        }

        foreach ($groups as $group) {
            $floor = Carbon::createFromFormat('!Y-m', $group['latest'])->addMonthNoOverflow()->startOfMonth();
            $accounts = $group['group']
                ? MembershipAccount::where('account_group_id', $group['group'])->get()
                : MembershipAccount::whereKey($group['account'])->get();
            foreach ($accounts as $account) {
                if (!$account->billing_backfill_floor || $account->billing_backfill_floor->lt($floor)) {
                    $account->billing_backfill_floor = $floor->toDateString();
                    $account->save();
                }
            }
        }
    }

    private function issue(string $sheet, ?int $row, string $field, string $message, string $origin = 'Validación'): array
    {
        return compact('sheet', 'row', 'field', 'message', 'origin');
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
