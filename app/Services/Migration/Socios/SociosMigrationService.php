<?php

namespace App\Services\Migration\Socios;

use App\Models\Administrator\Club;
use App\Models\Catalogs\CancellationReason;
use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Catalogs\Relationship;
use App\Models\Catalogs\State;
use App\Models\Context;
use App\Models\Members\Address;
use App\Models\Members\EmploymentInfo;
use App\Models\Members\Member;
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
use App\Services\MemberAccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SociosMigrationService
{
    public function __construct(
        private AccessProvisioningService $accessProvisioningService,
        private MemberAccessService $memberAccessService
    ) {}

    private const HEADERS = [
        'Personal' => ['NOMBRE', 'APELLIDO PATERNO', 'APELLIDO MATERNO', 'CORREO', 'ROL', 'CLUBES', 'SERIE DE CAJA'],
        'Usuarios' => ['ID DE USUARIO', 'NOMBRE', 'APELLIDO PATERNO', 'APELLIDO MATERNO', 'FECHA DE NACIMIENTO', 'SEXO', 'TELEFONO', 'CORREO', 'ESTADO CIVIL', 'NACIONALIDAD', 'PAIS DE NACIMIENTO', 'ESTADO DE NACIMIENTO', 'CIUDAD DE NACIMIENTO', 'OCUPACION', 'ESCUELA', 'CALLE Y NUMERO', 'COLONIA', 'CODIGO POSTAL', 'PAIS', 'ESTADO', 'CIUDAD', 'AÑOS EN LA CIUDAD', 'EMPRESA', 'DOMICILIO DE LA EMPRESA', 'TELEFONO DE LA EMPRESA'],
        'Membresias' => ['NUMERO DE CUENTA', 'CLUB', 'TIPO DE MEMBRESIA', 'INDIVIDUAL O FAMILIAR', 'ESTATUS', 'FECHA DE INICIO', 'FECHA DE TERMINO', 'CUOTA MENSUAL', 'GENERA COBRO', 'CUENTA EN EL OTRO PARQUE', 'TIPO DE MEMBRESIA ANTERIOR', 'CUENTA DE ORIGEN', 'MOTIVO DE SEPARACION', 'FECHA DE CANCELACION', 'TIPO DE CANCELACION', 'MOTIVO DE CANCELACION', 'ARCHIVO DE LA CARTA DE CANCELACION', 'NOMBRE O RAZON SOCIAL', 'RFC', 'USO DE CFDI', 'REGIMEN FISCAL', 'CODIGO POSTAL FISCAL'],
        'Integrantes' => ['NUMERO DE CUENTA', 'ID DE USUARIO', 'ES TITULAR', 'PARENTESCO', 'NUMERO DE TARJETA DE ACCESO'],
    ];

    public function run(string $file, bool $dryRun = false, bool $skipPersonal = false): array
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

            $this->validateRows($data);

            DB::beginTransaction();
            try {
                if (!$skipPersonal) {
                    $this->importPersonal($data['Personal']);
                }
                $this->importMembers($data['Usuarios']);
                $this->importMemberships($data['Membresias']);
                $this->importAccountMembers($data['Integrantes']);
                $this->linkAccounts($data['Membresias']);
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

            return [
                'Personal' => $skipPersonal ? 0 : count($data['Personal']),
                'Usuarios' => count($data['Usuarios']),
                'Membresias' => count($data['Membresias']),
                'Integrantes' => count($data['Integrantes']),
            ];
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

    private function validateRows(array $data): void
    {
        $errors = [];
        $memberIds = [];
        $accountNumbers = [];
        $holders = [];
        $accountMembers = [];
        $staffEmails = [];
        $staffCodes = [];

        foreach ($data['Personal'] as $row) {
            $label = "Personal fila {$row['_row']}";
            if ($row['NOMBRE'] === '' || $row['APELLIDO PATERNO'] === '' || $row['CLUBES'] === '') {
                $errors[] = "{$label}: faltan nombre, apellido o clubes.";
            }
            if ($row['CORREO'] === '' && $row['SERIE DE CAJA'] === '') {
                $errors[] = "{$label}: un cajero histórico sin correo necesita serie de caja.";
            }
            if ($row['CORREO'] !== '' && !filter_var($row['CORREO'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$label}: correo inválido.";
            }
            $email = mb_strtolower($row['CORREO']);
            $code = $this->key($row['SERIE DE CAJA']);
            if ($email !== '' && isset($staffEmails[$email])) {
                $errors[] = "{$label}: correo duplicado.";
            }
            if ($code !== '' && isset($staffCodes[$code])) {
                $errors[] = "{$label}: serie de caja duplicada.";
            }
            $staffEmails[$email] = true;
            if ($code !== '') {
                $staffCodes[$code] = true;
            }
        }

        foreach ($data['Usuarios'] as $row) {
            $id = $row['ID DE USUARIO'];
            $label = "Usuarios fila {$row['_row']}";
            if ($id === '' || $row['NOMBRE'] === '' || $row['APELLIDO PATERNO'] === '') {
                $errors[] = "{$label}: faltan ID, nombre o apellido.";
            }
            if ($id !== '' && isset($memberIds[$id])) {
                $errors[] = "{$label}: ID DE USUARIO duplicado ({$id}).";
            }
            if ($row['CORREO'] !== '' && !filter_var($row['CORREO'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$label}: correo inválido.";
            }
            if ($row['FECHA DE NACIMIENTO'] !== '' && !$this->dateIsValid($row['FECHA DE NACIMIENTO'])) {
                $errors[] = "{$label}: fecha de nacimiento inválida.";
            }
            if ($id !== '') {
                $memberIds[$id] = true;
            }
        }

        foreach ($data['Membresias'] as $row) {
            $number = $row['NUMERO DE CUENTA'];
            $label = "Membresias fila {$row['_row']}";
            if ($number === '' || $row['CLUB'] === '' || $row['TIPO DE MEMBRESIA'] === '' || $row['ESTATUS'] === '' || !$this->dateIsValid($row['FECHA DE INICIO'])) {
                $errors[] = "{$label}: faltan cuenta, club, tipo, estatus o fecha de inicio válida.";
            }
            if ($number !== '' && isset($accountNumbers[$number])) {
                $errors[] = "{$label}: NUMERO DE CUENTA duplicado ({$number}).";
            }
            if ($number !== '') {
                $accountNumbers[$number] = true;
                $holders[$number] = 0;
            }
        }

        foreach ($data['Integrantes'] as $row) {
            $number = $row['NUMERO DE CUENTA'];
            $id = $row['ID DE USUARIO'];
            $label = "Integrantes fila {$row['_row']}";
            if (!isset($accountNumbers[$number]) || !isset($memberIds[$id])) {
                $errors[] = "{$label}: cuenta o usuario no existe en esta plantilla.";
            }
            if (!in_array($this->key($row['ES TITULAR']), ['SI', 'NO'], true)) {
                $errors[] = "{$label}: ES TITULAR debe ser SI o NO.";
            }
            if ($this->key($row['ES TITULAR']) === 'SI' && isset($holders[$number])) {
                $holders[$number]++;
            }
            if ($this->key($row['ES TITULAR']) === 'NO' && $row['PARENTESCO'] === '') {
                $errors[] = "{$label}: falta parentesco.";
            }
            $pair = "{$number}|{$id}";
            if (isset($accountMembers[$pair])) {
                $errors[] = "{$label}: integrante duplicado en la cuenta.";
            }
            $accountMembers[$pair] = true;
        }

        foreach ($holders as $number => $count) {
            if ($count !== 1) {
                $errors[] = "Cuenta {$number}: debe tener exactamente un titular.";
            }
        }
        foreach ($data['Membresias'] as $row) {
            foreach (['CUENTA EN EL OTRO PARQUE', 'CUENTA DE ORIGEN'] as $field) {
                $target = $row[$field];
                if ($target !== '' && !isset($accountNumbers[$target])) {
                    $errors[] = "Membresias fila {$row['_row']}: {$field} {$target} no existe.";
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("La plantilla tiene errores:\n- " . implode("\n- ", array_slice($errors, 0, 30)));
        }
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
            $billable = $row['GENERA COBRO'] === '' ? true : $this->yesNo($row['GENERA COBRO'], $label);
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
            $account->cancellation_letter_path = $row['ARCHIVO DE LA CARTA DE CANCELACION'] ?: null;
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
                'end_date' => $row['FECHA DE TERMINO'] ?: null,
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
