<?php

namespace App\Services\Migration\Socios;

use App\Models\Administrator\Club;
use App\Models\Catalogs\CancellationReason;
use App\Models\Catalogs\City;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Catalogs\Relationship;
use App\Models\Catalogs\State;
use App\Models\Context;
use App\Models\Members\Address;
use App\Models\Members\EmploymentInfo;
use App\Models\Members\Member;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\AccountFiscalData;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\MobileApp\AppVariable;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessProvisioningService;
use App\Services\Billing\MembershipPricingService;
use App\Services\MemberAccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SociosMigrationService
{
    public function __construct(
        private AccessProvisioningService $accessProvisioningService,
        private MemberAccessService $memberAccessService,
        private MembershipPricingService $pricingService
    ) {}

    /** Avisos que no detienen la carga (cuota distinta a la del sistema, carta no encontrada...). */
    private array $warnings = [];

    /** Cartas de baja por subir al terminar la transaccion: [origen, destino, member_id, document_type_id, account_id]. */
    private array $pendingLetters = [];

    private ?string $filesFolder = null;

    public function warnings(): array
    {
        return $this->warnings;
    }

    private const HEADERS = [
        'Personal' => ['NOMBRE', 'APELLIDO PATERNO', 'APELLIDO MATERNO', 'CORREO', 'ROL', 'CLUBES', 'SERIE DE CAJA'],
        'Usuarios' => ['ID DE USUARIO', 'NOMBRE', 'APELLIDO PATERNO', 'APELLIDO MATERNO', 'FECHA DE NACIMIENTO', 'SEXO', 'TELEFONO', 'CORREO', 'ESTADO CIVIL', 'NACIONALIDAD', 'PAIS DE NACIMIENTO', 'ESTADO DE NACIMIENTO', 'CIUDAD DE NACIMIENTO', 'OCUPACION', 'ESCUELA', 'CALLE Y NUMERO', 'COLONIA', 'CODIGO POSTAL', 'PAIS', 'ESTADO', 'CIUDAD', 'AÑOS EN LA CIUDAD', 'EMPRESA', 'DOMICILIO DE LA EMPRESA', 'TELEFONO DE LA EMPRESA'],
        'Membresias' => ['NUMERO DE CUENTA', 'CLUB', 'TIPO DE MEMBRESIA', 'INDIVIDUAL O FAMILIAR', 'ESTATUS', 'FECHA DE INICIO', 'FECHA DE TERMINO', 'CUOTA MENSUAL', 'GENERA COBRO', 'CUENTA EN EL OTRO PARQUE', 'TIPO DE MEMBRESIA ANTERIOR', 'CUENTA DE ORIGEN', 'MOTIVO DE SEPARACION', 'FECHA DE CANCELACION', 'TIPO DE CANCELACION', 'MOTIVO DE CANCELACION', 'ARCHIVO DE LA CARTA DE CANCELACION', 'NOMBRE O RAZON SOCIAL', 'RFC', 'USO DE CFDI', 'REGIMEN FISCAL', 'CODIGO POSTAL FISCAL'],
        'Integrantes' => ['NUMERO DE CUENTA', 'ID DE USUARIO', 'ES TITULAR', 'PARENTESCO', 'NUMERO DE TARJETA DE ACCESO'],
    ];

    /**
     * Revisa la plantilla sin escribir nada: todos los errores (bloquean la carga) y avisos
     * (no la bloquean), cada uno con pestaña, fila y campo.
     *
     * @return array{counts: array<string,int>, errors: array, warnings: array}
     */
    public function review(string $file, bool $skipPersonal = false, ?string $filesFolder = null): array
    {
        $this->filesFolder = $filesFolder ?? database_path('data/ARCHIVOS');
        $data = $this->read($file);

        return $this->validate($data, $skipPersonal);
    }

    /**
     * @param string|null $filesFolder Carpeta con los archivos de la plantilla (CANCELACIONES/...). Por defecto database/data/ARCHIVOS.
     * @param string $disk Disco donde se suben las cartas de baja (spaces, igual que la baja desde el sistema).
     */
    public function run(string $file, bool $dryRun = false, bool $skipPersonal = false, ?string $filesFolder = null, string $disk = 'spaces'): array
    {
        $this->warnings = [];
        $this->pendingLetters = [];
        $this->filesFolder = $filesFolder ?? database_path('data/ARCHIVOS');
        $data = $this->read($file);

        $report = $this->validate($data, $skipPersonal);
        $this->warnings = $report['warnings'];
        if ($report['errors'] !== []) {
            $lines = array_map(
                fn ($issue) => "{$issue['sheet']} fila " . ($issue['row'] ?? '-') . " · {$issue['field']}: {$issue['message']}",
                array_slice($report['errors'], 0, 30)
            );
            throw new RuntimeException('La plantilla tiene ' . count($report['errors']) . " error(es); no se cargo nada:\n- " . implode("\n- ", $lines));
        }

        DB::beginTransaction();
        try {
            if (!$skipPersonal) {
                $this->importPersonal($data['Personal']);
            }
            $this->importMembers($data['Usuarios']);
            $this->importMemberships($data['Membresias']);
            $this->importAccountMembers($data['Integrantes']);
            $this->prepareLetters($data['Membresias']);
            $this->linkAccounts($data['Membresias']);
            $this->applyPricing($data['Membresias']);
            $this->provisionMobileUsers($data['Usuarios']);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (!$dryRun) {
            $this->uploadLetters($disk);
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
            foreach (self::HEADERS as $sheetName => $headers) {
                if (!$book->sheetNameExists($sheetName)) {
                    throw new RuntimeException("Falta la pestaña {$sheetName}.");
                }
                $data[$sheetName] = $this->rows($book->getSheetByName($sheetName), $headers);
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
            $header = $this->value($cell->getValue());
            if ($header !== '') {
                $columns[$header] = $cell->getColumn();
            }
        }

        foreach ($requiredHeaders as $header) {
            if (!isset($columns[$header])) {
                throw new RuntimeException("{$sheet->getTitle()}: falta la columna {$header}.");
            }
        }

        $rows = [];
        for ($number = 4; $number <= $sheet->getHighestDataRow(); $number++) {
            $row = [];
            foreach ($columns as $header => $column) {
                $cell = $sheet->getCell("{$column}{$number}");
                $value = $cell->getValue();
                if (is_numeric($value) && Date::isDateTime($cell)) {
                    $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                }
                $row[$header] = $this->value($value);
                if (in_array($header, ['FECHA DE NACIMIENTO', 'FECHA DE INICIO', 'FECHA DE TERMINO', 'FECHA DE CANCELACION'], true)) {
                    $row[$header] = $this->normalizedDate($row[$header]);
                }
            }
            if (count(array_filter($row, fn ($value) => $value !== '')) > 0) {
                $row['_row'] = $number;
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Reglas de la plantilla. Solo bloquea lo que rompe la carga o deja datos que el sistema
     * usa para calcular o dar continuidad (cuotas, edades, titulares, bajas, accesos, cuentas
     * de dos parques). Lo que se puede capturar despues sin afectar nada (apellido materno,
     * telefono, ocupacion...) no se revisa.
     */
    private function validate(array $data, bool $skipPersonal): array
    {
        $this->issues = [];
        $warnings = [];
        $warn = function (string $sheet, ?int $row, string $field, string $message) use (&$warnings) {
            $warnings[] = compact('sheet', 'row', 'field', 'message');
        };

        $usuarios = collect($data['Usuarios']);
        $cuentas = collect($data['Membresias']);
        $integrantes = collect($data['Integrantes']);
        $usuariosPorId = $usuarios->filter(fn ($r) => $r['ID DE USUARIO'] !== '')->keyBy('ID DE USUARIO');
        $cuentasPorNumero = $cuentas->filter(fn ($r) => $r['NUMERO DE CUENTA'] !== '')->keyBy('NUMERO DE CUENTA');
        $estatusVivo = ['ACTIVA', 'SUSPENDIDA', 'PENDIENTE'];
        $pareja = $this->partners($data['Membresias']);

        // ───────────── Personal ─────────────
        if (!$skipPersonal) {
            $correos = [];
            $series = [];
            $webContextId = Context::where('value', 'web')->value('id');
            $roles = Role::where('context_id', $webContextId)->get();
            foreach ($data['Personal'] as $row) {
                $n = $row['_row'];
                $this->required('Personal', $row, ['NOMBRE', 'CLUBES']);
                if ($row['CORREO'] === '' && $row['SERIE DE CAJA'] === '') {
                    $this->issue('Personal', $n, 'CORREO / SERIE DE CAJA', 'Los dos estan vacios. Un empleado necesita correo para entrar al sistema, o al menos su serie de caja para ligarle sus cobros historicos.', 'Falta dato');
                }
                if ($row['CORREO'] !== '') {
                    $correo = mb_strtolower($row['CORREO']);
                    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                        $this->issue('Personal', $n, 'CORREO', "\"{$row['CORREO']}\" no es un correo valido.", 'Dato invalido');
                    } elseif (isset($correos[$correo])) {
                        $this->issue('Personal', $n, 'CORREO', "Repetido con la fila {$correos[$correo]}.", 'Repetido');
                    }
                    $correos[$correo] ??= $n;
                }
                foreach (array_filter(array_map('trim', explode(',', $row['CLUBES']))) as $club) {
                    if (!$this->findClub($club)) {
                        $this->issue('Personal', $n, 'CLUBES', "El club \"{$club}\" no existe.", 'No existe en el sistema');
                    }
                }
                if ($row['ROL'] !== '' && !$roles->first(fn (Role $role) => $this->key($role->name) === $this->key($row['ROL']))) {
                    $this->issue('Personal', $n, 'ROL', "El rol \"{$row['ROL']}\" no existe.", 'No existe en el sistema');
                }
                $serie = $this->key($row['SERIE DE CAJA']);
                if ($serie !== '') {
                    if (isset($series[$serie])) {
                        $this->issue('Personal', $n, 'SERIE DE CAJA', "Repetida con la fila {$series[$serie]}; cada cajero tiene su propia serie.", 'Repetido');
                    }
                    $series[$serie] ??= $n;
                    $email = $row['CORREO'] !== '' ? mb_strtolower($row['CORREO']) : 'cajero-historico-' . Str::slug($serie) . '@migration.invalid';
                    if (User::where('code', $serie)->where('email', '!=', $email)->exists()) {
                        $this->issue('Personal', $n, 'SERIE DE CAJA', "La serie {$serie} ya pertenece a otro usuario del sistema.", 'Repetido');
                    }
                }
            }
        }

        // ───────────── Integrantes: datos que necesitan las otras pestañas ─────────────
        $titularDe = [];      // cuenta => id de usuario
        $cuentasDe = [];      // id de usuario => [cuentas]
        foreach ($integrantes as $row) {
            if ($row['ID DE USUARIO'] !== '' && $row['NUMERO DE CUENTA'] !== '') {
                $cuentasDe[$row['ID DE USUARIO']][] = $row['NUMERO DE CUENTA'];
                if ($this->key($row['ES TITULAR']) === 'SI') {
                    $titularDe[$row['NUMERO DE CUENTA']] ??= $row['ID DE USUARIO'];
                }
            }
        }
        $cuentaViva = fn (string $numero) => in_array($this->key($cuentasPorNumero->get($numero)['ESTATUS'] ?? ''), $estatusVivo, true);

        // ───────────── Usuarios ─────────────
        $ids = [];
        $correosApp = [];
        $usuariosExistentes = Member::whereNotNull('migration_origin_id')->pluck('user_id', 'migration_origin_id');
        foreach ($usuarios as $row) {
            $n = $row['_row'];
            $id = $row['ID DE USUARIO'];
            $this->required('Usuarios', $row, ['ID DE USUARIO', 'NOMBRE', 'APELLIDO PATERNO']);
            if ($id !== '') {
                if (isset($ids[$id])) {
                    $this->issue('Usuarios', $n, 'ID DE USUARIO', "Repetido con la fila {$ids[$id]}.", 'Repetido');
                }
                $ids[$id] ??= $n;
            }

            // La edad decide el acceso a la app, el precio de Solidaria y el de invitados
            if ($row['FECHA DE NACIMIENTO'] === '') {
                $this->issue('Usuarios', $n, 'FECHA DE NACIMIENTO', 'Vacia. Se necesita para la edad: precio de Solidaria, acceso a la app (14 años) y cambios por edad.', 'Falta dato');
            } elseif (!$this->dateIsValid($row['FECHA DE NACIMIENTO'])) {
                $this->issue('Usuarios', $n, 'FECHA DE NACIMIENTO', "\"{$row['FECHA DE NACIMIENTO']}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
            } elseif ($row['FECHA DE NACIMIENTO'] > now()->toDateString() || $row['FECHA DE NACIMIENTO'] < '1900-01-01') {
                $this->issue('Usuarios', $n, 'FECHA DE NACIMIENTO', "{$row['FECHA DE NACIMIENTO']} esta fuera de rango.", 'Dato invalido');
            }

            $susCuentas = $cuentasDe[$id] ?? [];
            if ($id !== '' && $susCuentas === []) {
                $warn('Usuarios', $n, 'ID DE USUARIO', "{$id} no aparece en Integrantes; se crea el usuario pero no queda en ninguna cuenta.");
            }
            $esTitularVivo = collect($titularDe)->filter(fn ($titular, $cuenta) => $titular === $id && $cuentaViva((string) $cuenta))->isNotEmpty();

            $correo = mb_strtolower($row['CORREO']);
            if ($correo === '' && $esTitularVivo) {
                $this->issue('Usuarios', $n, 'CORREO', 'Vacio y es titular de una cuenta activa. El titular necesita correo para entrar a la app y recibir avisos de cobro.', 'Falta dato');
            }
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $this->issue('Usuarios', $n, 'CORREO', "\"{$row['CORREO']}\" no es un correo valido.", 'Dato invalido');
            } elseif ($correo !== '') {
                // A quien recibe acceso a la app (14+ años y cuenta activa) se le crea un usuario: el correo no se puede repetir
                $edad = $this->dateIsValid($row['FECHA DE NACIMIENTO']) ? Carbon::parse($row['FECHA DE NACIMIENTO'])->age : null;
                $tendraApp = $edad !== null && $edad >= 14 && collect($susCuentas)->contains(fn ($c) => in_array($this->key($cuentasPorNumero->get($c)['ESTATUS'] ?? ''), ['ACTIVA', 'SUSPENDIDA'], true));
                if ($tendraApp) {
                    if (isset($correosApp[$correo])) {
                        $this->issue('Usuarios', $n, 'CORREO', "Repetido con la fila {$correosApp[$correo]}. Las dos personas tendran acceso a la app y cada una necesita su propio correo.", 'Repetido');
                    }
                    $correosApp[$correo] ??= $n;
                    $usuarioActual = $usuariosExistentes[$id] ?? null;
                    $owner = User::whereRaw('LOWER(email) = ?', [$correo])->value('id');
                    if ($owner && (int) $owner !== (int) $usuarioActual) {
                        $this->issue('Usuarios', $n, 'CORREO', "{$correo} ya lo usa otro usuario del sistema.", 'Repetido');
                    }
                }
            }

            if ($row['SEXO'] !== '' && !in_array($this->key($row['SEXO']), Member::GENDERS, true)) {
                $this->issue('Usuarios', $n, 'SEXO', 'Debe ser H o M.', 'Dato invalido');
            }
            if ($row['ESTADO CIVIL'] !== '' && !$this->findMarital($row['ESTADO CIVIL'])) {
                $this->issue('Usuarios', $n, 'ESTADO CIVIL', "\"{$row['ESTADO CIVIL']}\" no existe en el catalogo.", 'No existe en el sistema');
            }
            if ($row['NACIONALIDAD'] !== '' && !$this->findNationality($row['NACIONALIDAD'])) {
                $this->issue('Usuarios', $n, 'NACIONALIDAD', "\"{$row['NACIONALIDAD']}\" no existe en el catalogo.", 'No existe en el sistema');
            }
            $this->checkLocation('Usuarios', $n, $row['PAIS DE NACIMIENTO'], $row['ESTADO DE NACIMIENTO'], $row['CIUDAD DE NACIMIENTO'], ['PAIS DE NACIMIENTO', 'ESTADO DE NACIMIENTO', 'CIUDAD DE NACIMIENTO']);
            $this->checkLocation('Usuarios', $n, $row['PAIS'], $row['ESTADO'], $row['CIUDAD'], ['PAIS', 'ESTADO', 'CIUDAD']);
            if ($row['AÑOS EN LA CIUDAD'] !== '' && (!ctype_digit($row['AÑOS EN LA CIUDAD']) || (int) $row['AÑOS EN LA CIUDAD'] > 120)) {
                $this->issue('Usuarios', $n, 'AÑOS EN LA CIUDAD', 'Debe ser un numero entero de años.', 'Dato invalido');
            }
        }

        // ───────────── Membresias ─────────────
        $numeros = [];
        $tipos = [];
        foreach ($cuentas as $row) {
            $n = $row['_row'];
            $numero = $row['NUMERO DE CUENTA'];
            $this->required('Membresias', $row, ['NUMERO DE CUENTA', 'CLUB', 'TIPO DE MEMBRESIA', 'ESTATUS', 'FECHA DE INICIO', 'GENERA COBRO']);
            if ($numero !== '') {
                if (isset($numeros[$numero])) {
                    $this->issue('Membresias', $n, 'NUMERO DE CUENTA', "Repetido con la fila {$numeros[$numero]}.", 'Repetido');
                }
                $numeros[$numero] ??= $n;
            }

            $club = $row['CLUB'] !== '' ? $this->findClub($row['CLUB']) : null;
            if ($row['CLUB'] !== '' && !$club) {
                $this->issue('Membresias', $n, 'CLUB', "El club \"{$row['CLUB']}\" no existe.", 'No existe en el sistema');
            }
            $tipo = $club && $row['TIPO DE MEMBRESIA'] !== '' ? $this->findType($club->id, $row['TIPO DE MEMBRESIA']) : null;
            if ($club && $row['TIPO DE MEMBRESIA'] !== '' && !$tipo) {
                $this->issue('Membresias', $n, 'TIPO DE MEMBRESIA', "\"{$row['TIPO DE MEMBRESIA']}\" no existe en {$club->code}.", 'No existe en el sistema');
            }
            $tipos[$numero] = $tipo;
            if ($club && $numero !== '') {
                $existente = MembershipAccount::where('membership_number', $numero)->value('club_id');
                if ($existente && (int) $existente !== (int) $club->id) {
                    $this->issue('Membresias', $n, 'NUMERO DE CUENTA', "La cuenta {$numero} ya existe en el sistema en otro club.", 'Repetido');
                }
            }

            $estatus = $this->key($row['ESTATUS']);
            if ($estatus !== '' && !in_array($estatus, ['ACTIVA', 'SUSPENDIDA', 'CANCELADA', 'PENDIENTE'], true)) {
                $this->issue('Membresias', $n, 'ESTATUS', 'Debe ser ACTIVA, SUSPENDIDA, CANCELADA o PENDIENTE.', 'Dato invalido');
            }

            $modalidad = $this->key($row['INDIVIDUAL O FAMILIAR']);
            if ($modalidad !== '' && !in_array($modalidad, ['INDIVIDUAL', 'FAMILIAR'], true)) {
                $this->issue('Membresias', $n, 'INDIVIDUAL O FAMILIAR', 'Debe ser INDIVIDUAL o FAMILIAR.', 'Dato invalido');
            } elseif ($tipo && $modalidad !== '' && ($modalidad === 'FAMILIAR') !== (bool) $tipo->allows_multiple_members) {
                $this->issue('Membresias', $n, 'INDIVIDUAL O FAMILIAR', "Dice {$modalidad} pero el tipo {$tipo->name} es " . ($tipo->allows_multiple_members ? 'FAMILIAR' : 'INDIVIDUAL') . '.', 'Dato contradictorio');
            }

            // Fechas
            $inicio = $row['FECHA DE INICIO'];
            if ($inicio !== '' && !$this->dateIsValid($inicio)) {
                $this->issue('Membresias', $n, 'FECHA DE INICIO', "\"{$inicio}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
                $inicio = '';
            } elseif ($inicio !== '' && $estatus !== 'PENDIENTE' && $inicio > now()->toDateString()) {
                $this->issue('Membresias', $n, 'FECHA DE INICIO', "{$inicio} es una fecha futura y la cuenta no esta PENDIENTE.", 'Dato contradictorio');
            }
            if ($row['FECHA DE TERMINO'] !== '') {
                if (!$this->dateIsValid($row['FECHA DE TERMINO'])) {
                    $this->issue('Membresias', $n, 'FECHA DE TERMINO', "\"{$row['FECHA DE TERMINO']}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
                } elseif ($inicio !== '' && $row['FECHA DE TERMINO'] < $inicio) {
                    $this->issue('Membresias', $n, 'FECHA DE TERMINO', 'Es anterior a la FECHA DE INICIO.', 'Dato contradictorio');
                }
            }

            // Cobro
            $genera = $this->key($row['GENERA COBRO']);
            $cuota = $row['CUOTA MENSUAL'];
            if ($genera !== '' && !in_array($genera, ['SI', 'NO'], true)) {
                $this->issue('Membresias', $n, 'GENERA COBRO', 'Escriba SI o NO.', 'Dato invalido');
            }
            if ($cuota !== '' && (!is_numeric($cuota) || (float) $cuota < 0)) {
                $this->issue('Membresias', $n, 'CUOTA MENSUAL', "\"{$cuota}\" no es un importe valido.", 'Dato invalido');
            } elseif ($genera === 'SI' && ($cuota === '' || (float) $cuota <= 0)) {
                $this->issue('Membresias', $n, 'CUOTA MENSUAL', 'GENERA COBRO dice SI y la cuota esta vacia o en cero. Capture la cuota que paga hoy.', 'Falta dato');
            } elseif ($genera === 'NO' && $cuota !== '' && (float) $cuota != 0) {
                $this->issue('Membresias', $n, 'CUOTA MENSUAL', 'GENERA COBRO dice NO y se lleno la cuota. Deje la cuota vacia o cambie GENERA COBRO a SI.', 'Dato contradictorio');
            }
            if ($genera === 'NO' && !isset($pareja[$numero]) && in_array($estatus, ['ACTIVA', 'SUSPENDIDA'], true)) {
                $warn('Membresias', $n, 'GENERA COBRO', 'Cuenta de un solo parque sin cobro: el sistema no le generara mensualidades.');
            }

            // Baja
            $datosBaja = ['FECHA DE CANCELACION', 'TIPO DE CANCELACION', 'MOTIVO DE CANCELACION', 'ARCHIVO DE LA CARTA DE CANCELACION'];
            if ($estatus === 'CANCELADA') {
                $this->required('Membresias', $row, ['FECHA DE CANCELACION', 'TIPO DE CANCELACION', 'MOTIVO DE CANCELACION'], 'La cuenta esta CANCELADA y este campo esta vacio.');
                if ($row['FECHA DE CANCELACION'] !== '') {
                    if (!$this->dateIsValid($row['FECHA DE CANCELACION'])) {
                        $this->issue('Membresias', $n, 'FECHA DE CANCELACION', "\"{$row['FECHA DE CANCELACION']}\" no es una fecha valida (AAAA-MM-DD).", 'Dato invalido');
                    } elseif ($inicio !== '' && $row['FECHA DE CANCELACION'] < $inicio) {
                        $this->issue('Membresias', $n, 'FECHA DE CANCELACION', 'Es anterior a la FECHA DE INICIO.', 'Dato contradictorio');
                    } elseif ($row['FECHA DE CANCELACION'] > now()->toDateString()) {
                        $this->issue('Membresias', $n, 'FECHA DE CANCELACION', 'Es una fecha futura.', 'Dato contradictorio');
                    }
                }
                if ($row['TIPO DE CANCELACION'] !== '' && !in_array($this->key($row['TIPO DE CANCELACION']), ['VOLUNTARIA', 'SANCION'], true)) {
                    $this->issue('Membresias', $n, 'TIPO DE CANCELACION', 'Debe ser VOLUNTARIA o SANCION.', 'Dato invalido');
                }
                if ($row['MOTIVO DE CANCELACION'] !== '' && !CancellationReason::all()->first(fn ($r) => $this->key($r->name) === $this->key($row['MOTIVO DE CANCELACION']))) {
                    $this->issue('Membresias', $n, 'MOTIVO DE CANCELACION', "\"{$row['MOTIVO DE CANCELACION']}\" no existe en el catalogo de motivos.", 'No existe en el sistema');
                }
                $carta = $row['ARCHIVO DE LA CARTA DE CANCELACION'];
                if ($carta !== '' && !is_file(rtrim((string) $this->filesFolder, '\\/') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $carta))) {
                    $warn('Membresias', $n, 'ARCHIVO DE LA CARTA DE CANCELACION', "No se encontro {$carta} en la carpeta de archivos; la cuenta quedara sin carta.");
                }
            } else {
                foreach ($datosBaja as $campo) {
                    if ($row[$campo] !== '') {
                        $this->issue('Membresias', $n, $campo, "La cuenta dice {$row['ESTATUS']} y este campo tiene dato. Solo se llena si la cuenta esta CANCELADA.", 'Dato contradictorio');
                    }
                }
            }

            // Tipo anterior / cuenta de origen (Solidaria)
            $anterior = null;
            if ($row['TIPO DE MEMBRESIA ANTERIOR'] !== '' && $club) {
                $anterior = $this->findType($club->id, $row['TIPO DE MEMBRESIA ANTERIOR']);
                if (!$anterior) {
                    $this->issue('Membresias', $n, 'TIPO DE MEMBRESIA ANTERIOR', "\"{$row['TIPO DE MEMBRESIA ANTERIOR']}\" no existe en {$club->code}.", 'No existe en el sistema');
                }
            }
            if ($tipo && $tipo->requires_origin_family) {
                if ($row['TIPO DE MEMBRESIA ANTERIOR'] === '') {
                    $this->issue('Membresias', $n, 'TIPO DE MEMBRESIA ANTERIOR', "Vacio. El tipo {$tipo->name} viene de una membresia familiar y el precio depende de ese tipo.", 'Falta dato');
                } elseif ($anterior && !$anterior->allows_multiple_members) {
                    $this->issue('Membresias', $n, 'TIPO DE MEMBRESIA ANTERIOR', "{$tipo->name} debe venir de una membresia familiar y {$anterior->name} no lo es.", 'Dato contradictorio');
                }
            }
            if ($row['CUENTA DE ORIGEN'] !== '') {
                if (!$cuentasPorNumero->has($row['CUENTA DE ORIGEN'])) {
                    $this->issue('Membresias', $n, 'CUENTA DE ORIGEN', "{$row['CUENTA DE ORIGEN']} no existe en Membresias.", 'No existe en la plantilla');
                } elseif ($row['CUENTA DE ORIGEN'] === $numero) {
                    $this->issue('Membresias', $n, 'CUENTA DE ORIGEN', 'No puede ser la misma cuenta.', 'Dato contradictorio');
                }
            } elseif ($row['MOTIVO DE SEPARACION'] !== '') {
                $this->issue('Membresias', $n, 'MOTIVO DE SEPARACION', 'Tiene motivo de separacion y CUENTA DE ORIGEN esta vacia. Capture de que cuenta se separo o borre el motivo.', 'Dato contradictorio');
            }

            // Datos fiscales: todos o ninguno
            $fiscales = ['NOMBRE O RAZON SOCIAL', 'RFC', 'USO DE CFDI', 'REGIMEN FISCAL', 'CODIGO POSTAL FISCAL'];
            $llenos = array_filter($fiscales, fn ($campo) => $row[$campo] !== '');
            if ($llenos !== [] && count($llenos) < count($fiscales)) {
                foreach (array_diff($fiscales, $llenos) as $campo) {
                    $this->issue('Membresias', $n, $campo, 'Hay datos fiscales capturados y este falta; para facturar se necesitan los cinco.', 'Falta dato');
                }
            }
            if ($row['RFC'] !== '' && !preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', mb_strtoupper($row['RFC']))) {
                $this->issue('Membresias', $n, 'RFC', "\"{$row['RFC']}\" no tiene formato de RFC (12 o 13 caracteres).", 'Dato invalido');
            }
            if ($row['CODIGO POSTAL FISCAL'] !== '' && !preg_match('/^\d{5}$/', $row['CODIGO POSTAL FISCAL'])) {
                $this->issue('Membresias', $n, 'CODIGO POSTAL FISCAL', 'Debe tener 5 digitos.', 'Dato invalido');
            }

            // Titular
            if ($numero !== '' && !isset($titularDe[$numero])) {
                $this->issue('Membresias', $n, 'NUMERO DE CUENTA', 'La cuenta no tiene titular en Integrantes.', 'Falta dato');
            }
        }

        // Cuentas en dos parques
        foreach ($cuentas as $row) {
            $n = $row['_row'];
            $otroNumero = $row['CUENTA EN EL OTRO PARQUE'];
            if ($otroNumero === '') {
                continue;
            }
            $otra = $cuentasPorNumero->get($otroNumero);
            if (!$otra) {
                $this->issue('Membresias', $n, 'CUENTA EN EL OTRO PARQUE', "{$otroNumero} no existe en Membresias.", 'No existe en la plantilla');
                continue;
            }
            if ($otroNumero === $row['NUMERO DE CUENTA']) {
                $this->issue('Membresias', $n, 'CUENTA EN EL OTRO PARQUE', 'No puede ser la misma cuenta.', 'Dato contradictorio');
                continue;
            }
            if ($otra['CUENTA EN EL OTRO PARQUE'] !== '' && $otra['CUENTA EN EL OTRO PARQUE'] !== $row['NUMERO DE CUENTA']) {
                $this->issue('Membresias', $n, 'CUENTA EN EL OTRO PARQUE', "{$otroNumero} dice que su cuenta del otro parque es {$otra['CUENTA EN EL OTRO PARQUE']}, no {$row['NUMERO DE CUENTA']}.", 'Dato contradictorio');
            }
            if ($this->key($otra['CLUB']) !== '' && $this->findClub($otra['CLUB'])?->id === $this->findClub($row['CLUB'])?->id) {
                $this->issue('Membresias', $n, 'CUENTA EN EL OTRO PARQUE', "{$otroNumero} es del mismo club; debe ser una cuenta del otro parque.", 'Dato contradictorio');
            }
            $titular = $titularDe[$row['NUMERO DE CUENTA']] ?? null;
            if ($titular && isset($titularDe[$otroNumero]) && $titularDe[$otroNumero] !== $titular) {
                $this->issue('Membresias', $n, 'CUENTA EN EL OTRO PARQUE', "El titular de {$otroNumero} ({$titularDe[$otroNumero]}) no es el mismo que el de esta cuenta ({$titular}).", 'Dato contradictorio');
            }
            // Cada pareja se revisa una sola vez
            if ($otra['CUENTA EN EL OTRO PARQUE'] === $row['NUMERO DE CUENTA'] && strcmp($row['NUMERO DE CUENTA'], $otroNumero) > 0) {
                continue;
            }
            $vivas = in_array($this->key($row['ESTATUS']), ['ACTIVA', 'SUSPENDIDA'], true) && in_array($this->key($otra['ESTATUS']), ['ACTIVA', 'SUSPENDIDA'], true);
            $mia = $this->key($row['GENERA COBRO']);
            $suya = $this->key($otra['GENERA COBRO']);
            if ($vivas && $mia === 'SI' && $suya === 'SI') {
                $this->issue('Membresias', $n, 'GENERA COBRO', "{$row['NUMERO DE CUENTA']} y {$otroNumero} son la misma membresia en dos parques y las dos dicen SI. Solo una cobra la mensualidad de ambos parques.", 'Dato contradictorio');
            } elseif ($vivas && $mia === 'NO' && $suya === 'NO') {
                $this->issue('Membresias', $n, 'GENERA COBRO', "{$row['NUMERO DE CUENTA']} y {$otroNumero} son la misma membresia en dos parques y las dos dicen NO. Una de las dos debe cobrar.", 'Dato contradictorio');
            }
        }

        // Regla de precio (la misma que usa el alta)
        foreach ($cuentas as $row) {
            $tipo = $tipos[$row['NUMERO DE CUENTA']] ?? null;
            $club = $this->findClub($row['CLUB']);
            if (!$tipo || !$club || !in_array($this->key($row['GENERA COBRO']), ['SI', 'NO'], true)) {
                continue;
            }
            $anterior = $row['TIPO DE MEMBRESIA ANTERIOR'] !== '' ? $this->findType($club->id, $row['TIPO DE MEMBRESIA ANTERIOR']) : null;
            $titular = $usuariosPorId->get($titularDe[$row['NUMERO DE CUENTA']] ?? '');
            $edad = $titular && $this->dateIsValid($titular['FECHA DE NACIMIENTO']) ? Carbon::parse($titular['FECHA DE NACIMIENTO'])->age : null;
            $otra = isset($pareja[$row['NUMERO DE CUENTA']]) ? $cuentasPorNumero->get($pareja[$row['NUMERO DE CUENTA']]) : null;
            $otraClub = $otra ? $this->findClub($otra['CLUB']) : null;
            $otraTipo = $otra && $otraClub ? $this->findType($otraClub->id, $otra['TIPO DE MEMBRESIA']) : null;
            $otraViva = $otra && in_array($this->key($otra['ESTATUS']), ['ACTIVA', 'SUSPENDIDA'], true);
            $genera = $this->key($row['GENERA COBRO']) === 'SI';

            $precio = $this->resolvePrice(
                $club->id,
                $tipo,
                $anterior,
                $edad,
                $genera,
                $otraViva && $otraTipo ? new Membership(['club_id' => $otraClub->id, 'membership_type_id' => $otraTipo->id]) : null
            );
            if ($precio['error']) {
                $this->issue('Membresias', $row['_row'], 'TIPO DE MEMBRESIA', $precio['error'], 'Falta regla de precio');
            } elseif ($precio['fee'] === null) {
                $warn('Membresias', $row['_row'], 'TIPO DE MEMBRESIA', 'No hay regla de precio con cuota para este tipo; se usa la CUOTA MENSUAL de la plantilla.');
            } elseif ($genera && is_numeric($row['CUOTA MENSUAL']) && (float) $row['CUOTA MENSUAL'] > 0 && abs((float) $row['CUOTA MENSUAL'] - $precio['fee']) > 0.01) {
                $warn('Membresias', $row['_row'], 'CUOTA MENSUAL', "La plantilla dice {$row['CUOTA MENSUAL']}; con las reglas de precio del sistema la cuota es {$precio['fee']}. Se usa {$precio['fee']}.");
            }
        }

        // ───────────── Integrantes ─────────────
        $parejas = [];
        $titulares = [];
        $miembrosPorCuenta = [];
        $tarjetas = [];
        $tarjetasExistentes = DB::table('memberships.account_members as am')
            ->join('members.members as m', 'm.id', '=', 'am.member_id')
            ->whereNotNull('am.access_code')
            ->pluck('m.migration_origin_id', 'am.access_code');
        $clubDe = fn (string $numero) => $this->findClub($cuentasPorNumero->get($numero)['CLUB'] ?? '')?->id;
        $vivasPorClub = [];
        foreach ($integrantes as $row) {
            $n = $row['_row'];
            $numero = $row['NUMERO DE CUENTA'];
            $id = $row['ID DE USUARIO'];
            $this->required('Integrantes', $row, ['NUMERO DE CUENTA', 'ID DE USUARIO', 'ES TITULAR']);
            if ($numero !== '' && !$cuentasPorNumero->has($numero)) {
                $this->issue('Integrantes', $n, 'NUMERO DE CUENTA', "{$numero} no existe en Membresias.", 'No existe en la plantilla');
            }
            if ($id !== '' && !$usuariosPorId->has($id)) {
                $this->issue('Integrantes', $n, 'ID DE USUARIO', "{$id} no existe en Usuarios.", 'No existe en la plantilla');
            }
            $esTitular = $this->key($row['ES TITULAR']);
            if ($esTitular !== '' && !in_array($esTitular, ['SI', 'NO'], true)) {
                $this->issue('Integrantes', $n, 'ES TITULAR', 'Escriba SI o NO.', 'Dato invalido');
            }
            if ($esTitular === 'SI') {
                $titulares[$numero][] = $n;
                if ($row['PARENTESCO'] !== '') {
                    $this->issue('Integrantes', $n, 'PARENTESCO', 'ES TITULAR dice SI y se lleno el parentesco. El titular no lleva parentesco: borre el dato o cambie ES TITULAR a NO.', 'Dato contradictorio');
                }
            } elseif ($esTitular === 'NO') {
                if ($row['PARENTESCO'] === '') {
                    $this->issue('Integrantes', $n, 'PARENTESCO', 'ES TITULAR dice NO y el parentesco esta vacio.', 'Falta dato');
                } elseif (!$this->findRelationship($row['PARENTESCO'])) {
                    $this->issue('Integrantes', $n, 'PARENTESCO', "\"{$row['PARENTESCO']}\" no existe en el catalogo.", 'No existe en el sistema');
                }
            }
            $pareja = "{$numero}|{$id}";
            if (isset($parejas[$pareja])) {
                $this->issue('Integrantes', $n, 'ID DE USUARIO', "{$id} ya esta en la cuenta {$numero} (fila {$parejas[$pareja]}).", 'Repetido');
            }
            $parejas[$pareja] ??= $n;
            $miembrosPorCuenta[$numero][] = $n;

            // Una persona no puede estar en dos cuentas vivas del mismo club
            if ($numero !== '' && $id !== '' && $cuentaViva($numero) && ($clubId = $clubDe($numero))) {
                $previa = $vivasPorClub["{$id}|{$clubId}"] ?? null;
                if ($previa && $previa !== $numero) {
                    $this->issue('Integrantes', $n, 'NUMERO DE CUENTA', "{$id} ya esta en la cuenta {$previa} del mismo club; una persona solo puede estar en una cuenta activa por club.", 'Dato contradictorio');
                }
                $vivasPorClub["{$id}|{$clubId}"] ??= $numero;
            }

            $tarjeta = $row['NUMERO DE TARJETA DE ACCESO'];
            if ($tarjeta !== '') {
                if (isset($tarjetas[$tarjeta])) {
                    $this->issue('Integrantes', $n, 'NUMERO DE TARJETA DE ACCESO', "Repetida con la fila {$tarjetas[$tarjeta]}.", 'Repetido');
                }
                $tarjetas[$tarjeta] ??= $n;
                $cardOwner = $tarjetasExistentes[$tarjeta] ?? null;
                if ($tarjetasExistentes->has($tarjeta) && $cardOwner !== $id) {
                    $this->issue('Integrantes', $n, 'NUMERO DE TARJETA DE ACCESO', "La tarjeta {$tarjeta} ya la tiene otra persona en el sistema.", 'Repetido');
                }
            }
        }
        foreach ($cuentas as $row) {
            $numero = $row['NUMERO DE CUENTA'];
            if (count($titulares[$numero] ?? []) > 1) {
                $this->issue('Integrantes', $titulares[$numero][1], 'ES TITULAR', "La cuenta {$numero} tiene mas de un titular (filas " . implode(', ', $titulares[$numero]) . ').', 'Dato contradictorio');
            }
            $tipo = $tipos[$numero] ?? null;
            if ($tipo && !$tipo->allows_multiple_members && count($miembrosPorCuenta[$numero] ?? []) > 1) {
                $this->issue('Integrantes', $miembrosPorCuenta[$numero][1], 'NUMERO DE CUENTA', "La cuenta {$numero} es {$tipo->name} (individual) y tiene " . count($miembrosPorCuenta[$numero]) . ' integrantes.', 'Dato contradictorio');
            }
        }

        return [
            'counts' => [
                'Personal' => $skipPersonal ? 0 : count($data['Personal']),
                'Usuarios' => count($data['Usuarios']),
                'Membresias' => count($data['Membresias']),
                'Integrantes' => count($data['Integrantes']),
            ],
            'errors' => $this->issues,
            'warnings' => $warnings,
        ];
    }

    /** Cuenta del otro parque de cada cuenta, aunque el enlace venga escrito solo de un lado. */
    private function partners(array $rows): array
    {
        $partners = [];
        foreach ($rows as $row) {
            if ($row['CUENTA EN EL OTRO PARQUE'] !== '' && $row['NUMERO DE CUENTA'] !== '') {
                $partners[$row['NUMERO DE CUENTA']] = $row['CUENTA EN EL OTRO PARQUE'];
            }
        }
        foreach ($partners as $account => $other) {
            $partners[$other] ??= (string) $account;
        }
        return $partners;
    }

    private array $issues = [];

    private function issue(string $sheet, ?int $row, string $field, string $message, string $origin): void
    {
        $this->issues[] = compact('sheet', 'row', 'field', 'message', 'origin');
    }

    private function required(string $sheet, array $row, array $fields, ?string $message = null): void
    {
        foreach ($fields as $field) {
            if (($row[$field] ?? '') === '') {
                $this->issue($sheet, $row['_row'], $field, $message ?? 'Campo obligatorio vacio.', 'Falta dato');
            }
        }
    }

    private function checkLocation(string $sheet, int $row, string $country, string $state, string $city, array $fields): void
    {
        [$countryField, $stateField, $cityField] = $fields;
        $countryModel = $country !== '' ? $this->findCountry($country) : null;
        if ($country !== '' && !$countryModel) {
            $this->issue($sheet, $row, $countryField, "\"{$country}\" no existe en el catalogo de paises.", 'No existe en el sistema');
            return;
        }
        if ($state === '') {
            if ($city !== '') {
                $this->issue($sheet, $row, $stateField, 'Se lleno la ciudad y el estado esta vacio.', 'Falta dato');
            }
            return;
        }
        if (!$countryModel) {
            $this->issue($sheet, $row, $countryField, 'Se lleno el estado y el pais esta vacio.', 'Falta dato');
            return;
        }
        $stateModel = $this->findState($countryModel->id, $state);
        if (!$stateModel) {
            $this->issue($sheet, $row, $stateField, "\"{$state}\" no existe en {$country}.", 'No existe en el sistema');
            return;
        }
        if ($city !== '' && !$this->findCity($stateModel->id, $city)) {
            $this->issue($sheet, $row, $cityField, "\"{$city}\" no existe en {$state}.", 'No existe en el sistema');
        }
    }

    // ───────────── Catalogos (con cache) ─────────────
    private array $cache = [];

    private function findClub(string $value): ?Club
    {
        $this->cache['clubs'] ??= Club::all();
        return $this->cache['clubs']->first(fn (Club $item) => in_array($this->key($value), [$this->key($item->code), $this->key($item->name)], true));
    }

    private function findType(int $clubId, string $value): ?MembershipType
    {
        $this->cache['types'][$clubId] ??= MembershipType::where('club_id', $clubId)->get();
        return $this->cache['types'][$clubId]->first(fn (MembershipType $item) => in_array($this->key($value), [$this->key($item->name), $this->key($item->code)], true));
    }

    private function findMarital(string $value): ?MaritalStatus
    {
        $this->cache['marital'] ??= MaritalStatus::all();
        return $this->cache['marital']->first(fn (MaritalStatus $item) => $this->key($item->name) === $this->key($value));
    }

    private function findRelationship(string $value): ?Relationship
    {
        $this->cache['relationships'] ??= Relationship::all();
        return $this->cache['relationships']->first(fn (Relationship $item) => $this->key($item->name) === $this->key($value));
    }

    private function findNationality(string $value): ?Country
    {
        $this->cache['countries'] ??= Country::all();
        $wanted = $this->key($value);
        return $this->cache['countries']->first(function (Country $country) use ($wanted) {
            $name = $this->key($country->demonym ?? '');
            return $name === $wanted || ($name !== '' && rtrim($name, 'O') . 'A' === $wanted);
        });
    }

    private function findCountry(string $value): ?Country
    {
        $this->cache['countries'] ??= Country::all();
        return $this->cache['countries']->first(function (Country $item) use ($value) {
            $names = [$item->name, $item->translations['es-MX'] ?? null, $item->translations['es'] ?? null];
            return collect($names)->contains(fn ($name) => $name && $this->key($name) === $this->key($value));
        });
    }

    private function findState(int $countryId, string $value): ?State
    {
        $this->cache['states'][$countryId] ??= State::where('country_id', $countryId)->get();
        return $this->cache['states'][$countryId]->first(fn (State $item) => $this->key($item->name) === $this->key($value));
    }

    private function findCity(int $stateId, string $value): ?City
    {
        $this->cache['cities'][$stateId] ??= City::where('state_id', $stateId)->get();
        return $this->cache['cities'][$stateId]->first(fn (City $item) => $this->key($item->name) === $this->key($value));
    }

    /**
     * Regla de precio como en el alta. Cuenta en dos parques que cobra: paquete interclub o regla
     * de "ambos parques"; las demas, su regla propia.
     *
     * @return array{package: ?\App\Models\Memberships\InterclubPackageRule, rule: ?\App\Models\Memberships\PricingRule, fee: ?float, error: ?string}
     */
    private function resolvePrice(int $clubId, MembershipType $type, ?MembershipType $previousType, ?int $age, bool $billable, ?Membership $other): array
    {
        $multipleClubs = $other !== null && $billable;
        $package = $multipleClubs
            ? $this->pricingService->resolveInterclubPackageRuleBetween(new Membership(['club_id' => $clubId, 'membership_type_id' => $type->id]), $other)
            : null;
        $rule = $package ? null : $this->pricingService->resolvePricingRule(
            membershipTypeId: $type->id,
            fromMembershipTypeId: $previousType?->id,
            age: $this->pricingService->shouldApplyAgeFilter($type) ? $age : null,
            hasMultipleClubs: $multipleClubs
        );

        $error = null;
        if ($multipleClubs && !$package && !$rule?->requires_multiple_clubs) {
            $otherType = MembershipType::find($other->membership_type_id);
            $error = "No existe paquete interclub ni regla de precio de ambos parques para {$type->name} con {$otherType?->name}. Sin ella la cuenta no se cobra como de ambos parques; revise las reglas de precio del sistema.";
        }
        $fee = $package?->resolveMonthlyFee() ?? $rule?->resolveMonthlyFee();

        return ['package' => $package, 'rule' => $rule, 'fee' => $fee !== null ? round((float) $fee, 2) : null, 'error' => $error];
    }

    private function importPersonal(array $rows): void
    {
        $webContextId = Context::where('value', 'web')->value('id');

        foreach ($rows as $row) {
            $clubs = [];
            foreach (explode(',', $row['CLUBES']) as $clubName) {
                $clubs[] = $this->club(trim($clubName), "Personal fila {$row['_row']}")->id;
            }
            $email = mb_strtolower($row['CORREO']);
            $code = $this->key($row['SERIE DE CAJA']);
            if ($email === '') {
                $email = 'cajero-historico-' . Str::slug($code) . '@migration.invalid';
            }

            $user = User::firstOrNew(['email' => $email]);
            $user->name = trim($row['NOMBRE'] . ' ' . $row['APELLIDO PATERNO'] . ' ' . ($row['APELLIDO MATERNO'] ?? ''));
            if (!$user->exists) {
                $user->password = Str::random(48);
            }
            if ($code !== '') {
                $other = User::where('code', $code)->where('email', '!=', $email)->exists();
                if ($other) {
                    throw new RuntimeException("Personal fila {$row['_row']}: la serie de caja {$code} ya pertenece a otro usuario.");
                }
                $user->code = $code;
            }
            $user->save();
            $user->clubs()->sync(array_unique($clubs));

            if ($row['ROL'] !== '') {
                $role = Role::where('context_id', $webContextId)->get()
                    ->first(fn (Role $item) => $this->key($item->name) === $this->key($row['ROL']));
                if (!$role) {
                    throw new RuntimeException("Personal fila {$row['_row']}: rol {$row['ROL']} no existe en los seeders.");
                }
                $user->syncRoles([$role]);
            } else {
                $user->syncRoles([]);
            }
        }
    }

    private function importMembers(array $rows): void
    {
        foreach ($rows as $row) {
            $label = "Usuarios fila {$row['_row']}";
            $birth = $this->location($row['PAIS DE NACIMIENTO'], $row['ESTADO DE NACIMIENTO'], $row['CIUDAD DE NACIMIENTO'], $label);
            $address = $this->location($row['PAIS'], $row['ESTADO'], $row['CIUDAD'], $label);
            $nationality = null;
            if ($row['NACIONALIDAD'] !== '') {
                $nationality = Country::all()->first(function (Country $country) use ($row) {
                    $name = $this->key($country->demonym ?? '');
                    $wanted = $this->key($row['NACIONALIDAD']);
                    return $name === $wanted || ($name !== '' && rtrim($name, 'O') . 'A' === $wanted);
                });
                if (!$nationality) {
                    throw new RuntimeException("{$label}: nacionalidad {$row['NACIONALIDAD']} no existe.");
                }
            }
            $marital = null;
            if ($row['ESTADO CIVIL'] !== '') {
                $marital = MaritalStatus::all()->first(fn (MaritalStatus $item) => $this->key($item->name) === $this->key($row['ESTADO CIVIL']));
                if (!$marital) {
                    throw new RuntimeException("{$label}: estado civil {$row['ESTADO CIVIL']} no existe.");
                }
            }
            $gender = $this->key($row['SEXO']);
            if ($gender !== '' && !in_array($gender, Member::GENDERS, true)) {
                throw new RuntimeException("{$label}: sexo debe ser H o M.");
            }

            $member = Member::updateOrCreate(['migration_origin_id' => $row['ID DE USUARIO']], [
                'first_name' => $row['NOMBRE'],
                'last_name' => $row['APELLIDO PATERNO'],
                'second_last_name' => $row['APELLIDO MATERNO'] ?: null,
                'birthdate' => $row['FECHA DE NACIMIENTO'] ?: null,
                'gender' => $gender ?: null,
                'phone' => $row['TELEFONO'] ?: null,
                'email' => $row['CORREO'] ?: null,
                'marital_status_id' => $marital?->id,
                'nationality_id' => $nationality?->id,
                'birth_country_id' => $birth[0],
                'birth_state_id' => $birth[1],
                'birth_city_id' => $birth[2],
                'occupation' => $row['OCUPACION'] ?: null,
                'school_name' => $row['ESCUELA'] ?: null,
            ]);

            if ($row['CALLE Y NUMERO'] !== '' || $row['COLONIA'] !== '' || $row['CODIGO POSTAL'] !== '') {
                Address::updateOrCreate(['member_id' => $member->id, 'is_primary' => true], [
                    'street' => $row['CALLE Y NUMERO'] ?: null,
                    'neighborhood' => $row['COLONIA'] ?: null,
                    'postal_code' => $row['CODIGO POSTAL'] ?: null,
                    'country_id' => $address[0],
                    'state_id' => $address[1],
                    'city_id' => $address[2],
                    'years_in_city' => $row['AÑOS EN LA CIUDAD'] !== '' ? (int) $row['AÑOS EN LA CIUDAD'] : null,
                    'is_primary' => true,
                ]);
            }
            if ($row['EMPRESA'] !== '' || $row['DOMICILIO DE LA EMPRESA'] !== '' || $row['TELEFONO DE LA EMPRESA'] !== '') {
                EmploymentInfo::updateOrCreate(['member_id' => $member->id], [
                    'company_name' => $row['EMPRESA'] ?: null,
                    'company_address' => $row['DOMICILIO DE LA EMPRESA'] ?: null,
                    'company_phone' => $row['TELEFONO DE LA EMPRESA'] ?: null,
                ]);
            }
        }
    }

    private function importMemberships(array $rows): void
    {
        foreach ($rows as $row) {
            $label = "Membresias fila {$row['_row']}";
            $club = $this->club($row['CLUB'], $label);
            $type = MembershipType::where('club_id', $club->id)->get()
                ->first(fn (MembershipType $item) => in_array($this->key($row['TIPO DE MEMBRESIA']), [$this->key($item->name), $this->key($item->code)], true));
            if (!$type) {
                throw new RuntimeException("{$label}: tipo de membresía {$row['TIPO DE MEMBRESIA']} no existe para {$club->code}.");
            }
            $previousType = null;
            if ($row['TIPO DE MEMBRESIA ANTERIOR'] !== '') {
                $previousType = MembershipType::where('club_id', $club->id)->get()
                    ->first(fn (MembershipType $item) => in_array($this->key($row['TIPO DE MEMBRESIA ANTERIOR']), [$this->key($item->name), $this->key($item->code)], true));
                if (!$previousType) {
                    throw new RuntimeException("{$label}: tipo de membresía anterior no existe para {$club->code}.");
                }
            }
            $status = match ($this->key($row['ESTATUS'])) {
                'ACTIVA' => 'active', 'SUSPENDIDA' => 'suspended', 'CANCELADA' => 'cancelled', 'PENDIENTE' => 'pending',
                default => throw new RuntimeException("{$label}: estatus inválido."),
            };
            $accountType = match ($this->key($row['INDIVIDUAL O FAMILIAR'])) {
                'INDIVIDUAL' => 'individual', 'FAMILIAR' => 'family', '' => $type->allows_multiple_members ? 'family' : 'individual',
                default => throw new RuntimeException("{$label}: INDIVIDUAL O FAMILIAR inválido."),
            };
            $fee = $row['CUOTA MENSUAL'] !== '' ? $this->amount($row['CUOTA MENSUAL'], $label) : 0;
            $billable = $this->yesNo($row['GENERA COBRO'], $label);
            if ($row['FECHA DE TERMINO'] !== '' && !$this->dateIsValid($row['FECHA DE TERMINO'])) {
                throw new RuntimeException("{$label}: fecha de término inválida.");
            }

            $account = MembershipAccount::firstOrNew(['membership_number' => $row['NUMERO DE CUENTA']]);
            if ($account->exists && (int) $account->club_id !== (int) $club->id) {
                throw new RuntimeException("{$label}: la cuenta ya existe en otro club.");
            }
            $account->club_id = $club->id;
            $account->account_type = $accountType;
            $account->status = $status;
            $account->cancelled_at = $status === 'cancelled' ? ($row['FECHA DE CANCELACION'] ?: null) : null;
            $account->cancellation_type = $status === 'cancelled' ? match ($this->key($row['TIPO DE CANCELACION'])) {
                'VOLUNTARIA' => 'voluntary', 'SANCION' => 'sanction',
                default => throw new RuntimeException("{$label}: falta tipo de cancelación."),
            } : null;
            if ($status === 'cancelled' && !$this->dateIsValid($row['FECHA DE CANCELACION'])) {
                throw new RuntimeException("{$label}: falta fecha de cancelación válida.");
            }
            $reason = null;
            if ($status === 'cancelled') {
                $reason = CancellationReason::all()->first(fn (CancellationReason $item) => $this->key($item->name) === $this->key($row['MOTIVO DE CANCELACION']));
                if (!$reason) {
                    throw new RuntimeException("{$label}: motivo de cancelación no existe en los seeders.");
                }
            }
            $account->cancellation_reason_id = $reason?->id;
            if ($status !== 'cancelled') {
                $account->cancellation_letter_path = null;
            }
            $account->save();

            $membership = Membership::updateOrCreate(['membership_account_id' => $account->id, 'club_id' => $club->id], [
                'membership_type_id' => $type->id,
                'origin_membership_type_id' => $previousType?->id,
                'is_primary' => true,
                'is_billable' => $billable,
                'monthly_fee' => $fee,
                'monthly_fee_total' => $fee,
                'monthly_fee_share' => $fee,
                'billing_split_mode' => 'single',
                'start_date' => $row['FECHA DE INICIO'],
                // Igual que AccountCancellationController: al dar de baja, la membresia termina ese dia
                // Pase mensual sin fecha de termino: igual que el alta, inicio + vigencia del tipo
                'end_date' => $status === 'cancelled'
                    ? $row['FECHA DE CANCELACION']
                    : ($row['FECHA DE TERMINO'] ?: ($type->validity_months
                        ? Carbon::parse($row['FECHA DE INICIO'])->addMonthsNoOverflow($type->validity_months)->toDateString()
                        : null)),
                'status' => $status,
            ]);
            if (!DB::table('memberships.membership_history')->where('membership_id', $membership->id)->exists()) {
                DB::table('memberships.membership_history')->insert([
                    'membership_id' => $membership->id,
                    'old_membership_type_id' => $previousType?->id,
                    'new_membership_type_id' => $type->id,
                    'effective_date' => $row['FECHA DE INICIO'],
                    'reason' => 'Alta histórica migrada',
                    'new_monthly_fee' => $fee,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // La baja se registra con la misma razon que usa el sistema: con ella
            // MembershipChargeService::resolveCancelledPeriods sabe que meses no se cobran
            // (por ejemplo, si despues se reactiva la cuenta).
            if ($status === 'cancelled' && !DB::table('memberships.membership_history')
                ->where('membership_id', $membership->id)->where('reason', 'Baja voluntaria de cuenta')->exists()) {
                DB::table('memberships.membership_history')->insert([
                    'membership_id' => $membership->id,
                    'old_membership_type_id' => $type->id,
                    'new_membership_type_id' => $type->id,
                    'effective_date' => $row['FECHA DE CANCELACION'],
                    'reason' => 'Baja voluntaria de cuenta',
                    'previous_monthly_fee' => $fee,
                    'new_monthly_fee' => null,
                    'metadata' => json_encode(['cancellation_type' => $account->cancellation_type, 'migration_source' => 'cliente']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $fiscal = array_filter(array_map(fn ($name) => $row[$name], ['NOMBRE O RAZON SOCIAL', 'RFC', 'USO DE CFDI', 'REGIMEN FISCAL', 'CODIGO POSTAL FISCAL']));
            if ($fiscal !== []) {
                if (count($fiscal) !== 5) {
                    throw new RuntimeException("{$label}: los cinco datos fiscales deben estar completos.");
                }
                AccountFiscalData::updateOrCreate(['membership_account_id' => $account->id], [
                    'fiscal_name' => $row['NOMBRE O RAZON SOCIAL'],
                    'rfc' => $row['RFC'],
                    'cfdi_use' => $row['USO DE CFDI'],
                    'fiscal_regime' => $row['REGIMEN FISCAL'],
                    'postal_code' => $row['CODIGO POSTAL FISCAL'],
                ]);
            }
        }
    }

    private function importAccountMembers(array $rows): void
    {
        foreach ($rows as $row) {
            $label = "Integrantes fila {$row['_row']}";
            $account = MembershipAccount::where('membership_number', $row['NUMERO DE CUENTA'])->firstOrFail();
            $member = Member::where('migration_origin_id', $row['ID DE USUARIO'])->firstOrFail();
            $isHolder = $this->yesNo($row['ES TITULAR'], $label);
            $relationship = null;
            if (!$isHolder) {
                $relationship = Relationship::all()->first(fn (Relationship $item) => $this->key($item->name) === $this->key($row['PARENTESCO']));
                if (!$relationship) {
                    throw new RuntimeException("{$label}: parentesco {$row['PARENTESCO']} no existe.");
                }
            }
            $accountMember = MembershipAccountMember::firstOrNew(['membership_account_id' => $account->id, 'member_id' => $member->id]);
            $accountMember->is_primary_holder = $isHolder;
            $accountMember->relationship_id = $relationship?->id;
            if ($row['NUMERO DE TARJETA DE ACCESO'] !== '') {
                $used = MembershipAccountMember::where('access_code', $row['NUMERO DE TARJETA DE ACCESO'])
                    ->when($accountMember->exists, fn ($query) => $query->where('id', '!=', $accountMember->id))->exists();
                if ($used) {
                    throw new RuntimeException("{$label}: número de tarjeta ya utilizado.");
                }
                $accountMember->access_code = $row['NUMERO DE TARJETA DE ACCESO'];
            } elseif (!$accountMember->access_code) {
                $accountMember->access_code = $this->accessProvisioningService->generateUniqueCardNumber();
            }
            if (!$accountMember->access_valid_until) {
                $accountMember->access_valid_until = now()->addYears(10);
            }
            $accountMember->save();
        }
    }

    private function provisionMobileUsers(array $rows): void
    {
        $defaultPassword = AppVariable::whereNull('club_id')->where('name', 'default_user_password')->value('value');

        foreach ($rows as $row) {
            if ($row['CORREO'] === '' || $row['FECHA DE NACIMIENTO'] === '') {
                continue;
            }
            if (Carbon::parse($row['FECHA DE NACIMIENTO'])->age < 14) {
                continue;
            }

            $member = Member::where('migration_origin_id', $row['ID DE USUARIO'])->firstOrFail();
            $hasActiveAccount = $member->accountMemberships()
                ->whereHas('membershipAccount.memberships', fn ($query) => $query->whereIn('status', ['active', 'suspended']))
                ->exists();
            if (!$hasActiveAccount) {
                continue;
            }
            if (!$defaultPassword) {
                throw new RuntimeException('Falta la variable global default_user_password para crear accesos a la app.');
            }

            $email = mb_strtolower($row['CORREO']);
            $user = $member->user;
            if (!$user) {
                if (User::where('email', $email)->exists()) {
                    throw new RuntimeException("Usuarios fila {$row['_row']}: el correo {$email} ya pertenece a otro usuario.");
                }
                $user = User::create([
                    'name' => $member->full_name,
                    'email' => $email,
                    'password' => $defaultPassword,
                ]);
                $member->update(['user_id' => $user->id]);
            }
            $this->memberAccessService->syncMobileRoles($member->fresh());
        }
    }

    private function linkAccounts(array $rows): void
    {
        foreach ($rows as $row) {
            $account = MembershipAccount::where('membership_number', $row['NUMERO DE CUENTA'])->firstOrFail();
            if ($row['CUENTA DE ORIGEN'] !== '') {
                $origin = MembershipAccount::where('membership_number', $row['CUENTA DE ORIGEN'])->firstOrFail();
                $account->origin_account_id = $origin->id;
                $account->separation_reason = $row['MOTIVO DE SEPARACION'] ?: null;
            }
            if ($row['CUENTA EN EL OTRO PARQUE'] !== '') {
                $other = MembershipAccount::where('membership_number', $row['CUENTA EN EL OTRO PARQUE'])->firstOrFail();
                $holder = $account->primaryHolder?->member_id;
                if ($holder === null || $holder !== $other->primaryHolder?->member_id || $account->club_id === $other->club_id) {
                    throw new RuntimeException("Membresias fila {$row['_row']}: la cuenta del otro parque debe tener el mismo titular y otro club.");
                }
                $groupId = $account->account_group_id ?: $other->account_group_id;
                if (!$groupId) {
                    $groupId = MembershipAccountGroup::create(['status' => 'active'])->id;
                }
                $account->account_group_id = $groupId;
                $other->account_group_id = $groupId;
                $other->save();
            } elseif (!$account->account_group_id) {
                $account->account_group_id = MembershipAccountGroup::create(['status' => 'active'])->id;
            }
            $account->save();
        }
    }

    /**
     * Cuota y regla de precio como en el alta (MemberController::resolveApplicablePricing):
     *  - Cuenta de un parque: regla por tipo, tipo anterior y edad del titular (solo Solidaria).
     *  - Cuenta en dos parques: la que GENERA COBRO lleva el paquete interclub o la regla de
     *    "ambos parques" (requires_multiple_clubs); la otra conserva su regla propia y no cobra.
     * Sin esa regla, Cobranza (CollectionController::resolveGroupAccountIds) no junta las dos
     * cuentas y la mensualidad no se ve ni se cobra desde el otro parque.
     * Los errores y avisos ya se reportaron en validate(); aqui solo se aplica.
     */
    private function applyPricing(array $rows): void
    {
        $partners = $this->partners($rows);
        foreach ($rows as $row) {
            $account = MembershipAccount::with(['memberships.membershipType', 'primaryHolder.member'])
                ->where('membership_number', $row['NUMERO DE CUENTA'])->firstOrFail();
            $membership = $account->memberships->first();
            $previousType = $membership->origin_membership_type_id ? MembershipType::find($membership->origin_membership_type_id) : null;
            $birthdate = $account->primaryHolder?->member?->birthdate;

            $other = null;
            if (isset($partners[$row['NUMERO DE CUENTA']])) {
                $other = MembershipAccount::with('memberships')
                    ->where('membership_number', $partners[$row['NUMERO DE CUENTA']])->first()?->memberships->first();
                if ($other && !in_array($other->status, ['active', 'suspended'], true)) {
                    $other = null;
                }
            }

            $price = $this->resolvePrice(
                (int) $membership->club_id,
                $membership->membershipType,
                $previousType,
                $birthdate ? Carbon::parse($birthdate)->age : null,
                (bool) $membership->is_billable,
                $other
            );
            if ($price['error']) {
                throw new RuntimeException("Membresias fila {$row['_row']}: {$price['error']}");
            }
            if ($price['fee'] === null) {
                continue; // sin regla: se queda la cuota de la plantilla
            }

            $membership->update([
                'monthly_fee' => $price['fee'],
                'monthly_fee_total' => $price['fee'],
                'monthly_fee_share' => $price['fee'],
                'billing_split_mode' => 'single',
                'pricing_rule_id' => $price['rule']?->id,
                'interclub_package_rule_id' => $price['package']?->id,
            ]);
            DB::table('memberships.membership_history')
                ->where('membership_id', $membership->id)
                ->where('reason', 'Alta histórica migrada')
                ->update(['new_monthly_fee' => $price['fee']]);
            DB::table('memberships.membership_history')
                ->where('membership_id', $membership->id)
                ->where('reason', 'Baja voluntaria de cuenta')
                ->update(['previous_monthly_fee' => $price['fee']]);
        }
    }

    /**
     * Carta de baja: como en AccountCancellationController, queda como documento del titular
     * en members/{titular}/{tipo}/{uuid}.ext y su ruta en la cuenta. El archivo se busca en la
     * carpeta de archivos de la plantilla y se sube al terminar la transaccion.
     */
    private function prepareLetters(array $rows): void
    {
        $letterType = DocumentType::where('code', 'carta_baja')->first();
        $slug = $letterType ? Str::slug($letterType->name) : 'carta-baja';

        foreach ($rows as $row) {
            $letter = $row['ARCHIVO DE LA CARTA DE CANCELACION'];
            if ($letter === '' || $this->key($row['ESTATUS']) !== 'CANCELADA') {
                continue;
            }
            $account = MembershipAccount::with('primaryHolder')->where('membership_number', $row['NUMERO DE CUENTA'])->firstOrFail();
            if ($account->cancellation_letter_path) {
                continue; // ya se subio en una corrida anterior
            }
            $source = rtrim((string) $this->filesFolder, '\\/') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $letter);
            if (!is_file($source)) {
                $this->warn('Membresias', $row['_row'], 'ARCHIVO DE LA CARTA DE CANCELACION', "No se encontro el archivo {$letter} en {$this->filesFolder}; la cuenta queda sin carta.");
                continue;
            }
            $holderId = $account->primaryHolder?->member_id;
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $path = "members/{$holderId}/{$slug}/" . Str::uuid() . ".{$extension}";

            $account->update(['cancellation_letter_path' => $path]);
            MemberDocument::create(['member_id' => $holderId, 'document_type_id' => $letterType?->id, 'file_path' => $path]);
            $this->pendingLetters[] = [$source, $path, $row['_row']];
        }
    }

    private function uploadLetters(string $disk): void
    {
        foreach ($this->pendingLetters as [$source, $path, $row]) {
            try {
                if (Storage::disk($disk)->put($path, file_get_contents($source)) === false) {
                    throw new RuntimeException('el almacenamiento rechazo el archivo');
                }
            } catch (\Throwable $e) {
                $this->warn('Membresias', $row, 'ARCHIVO DE LA CARTA DE CANCELACION', "No se pudo subir {$source}: {$e->getMessage()}");
            }
        }
    }

    private function warn(string $sheet, ?int $row, string $field, string $message): void
    {
        $this->warnings[] = compact('sheet', 'row', 'field', 'message');
    }

    private function club(string $value, string $label): Club
    {
        $club = Club::all()->first(fn (Club $item) => in_array($this->key($value), [$this->key($item->code), $this->key($item->name)], true));
        return $club ?? throw new RuntimeException("{$label}: club {$value} no existe en los seeders.");
    }

    private function location(string $countryName, string $stateName, string $cityName, string $label): array
    {
        $country = null;
        $state = null;
        $city = null;
        if ($countryName !== '') {
            $country = Country::all()->first(function (Country $item) use ($countryName) {
                $names = [$item->name, $item->translations['es-MX'] ?? null, $item->translations['es'] ?? null];
                return collect($names)->contains(fn ($name) => $name && $this->key($name) === $this->key($countryName));
            });
            if (!$country) {
                throw new RuntimeException("{$label}: país {$countryName} no existe.");
            }
        }
        if ($stateName !== '') {
            if (!$country) {
                throw new RuntimeException("{$label}: estado sin país.");
            }
            $state = State::where('country_id', $country->id)->get()->first(fn (State $item) => $this->key($item->name) === $this->key($stateName));
            if (!$state) {
                throw new RuntimeException("{$label}: estado {$stateName} no existe.");
            }
        }
        if ($cityName !== '') {
            if (!$state) {
                throw new RuntimeException("{$label}: ciudad sin estado.");
            }
            $city = City::where('state_id', $state->id)->get()->first(fn (City $item) => $this->key($item->name) === $this->key($cityName));
            if (!$city) {
                throw new RuntimeException("{$label}: ciudad {$cityName} no existe.");
            }
        }
        return [$country?->id, $state?->id, $city?->id];
    }

    private function amount(string $value, string $label): float
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new RuntimeException("{$label}: importe inválido.");
        }
        return round((float) $value, 2);
    }

    private function yesNo(string $value, string $label): bool
    {
        return match ($this->key($value)) {
            'SI' => true, 'NO' => false,
            default => throw new RuntimeException("{$label}: se esperaba SI o NO."),
        };
    }

    private function dateIsValid(string $value): bool
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

    private function normalizedDate(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return sprintf('%04d-%02d-%02d', $parts[1], $parts[2], $parts[3]);
        }
        return $value;
    }

    private function key(?string $value): string
    {
        return mb_strtoupper(trim(Str::ascii($value ?? '')));
    }

    private function value(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
