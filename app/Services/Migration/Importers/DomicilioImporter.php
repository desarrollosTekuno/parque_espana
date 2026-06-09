<?php

namespace App\Services\Migration\Importers;

use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\State;
use App\Models\Members\Address;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DomicilioImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Domicilios';
    }

    public function sheetIndex(): int
    {
        return 4; // "4. Domicilios"
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

        foreach ($this->rows($sheet, $columns) as $rowNum => $row) {
            try {
                $originId = trim($row['ID_ORIGEN'] ?? '');

                if (empty($originId)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'ID_ORIGEN vacío.'];
                    continue;
                }

                $memberId = $context->memberId($originId);
                if (!$memberId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Usuario '{$originId}' no encontrado."];
                    continue;
                }

                [$countryId, $stateId, $cityId] = $this->resolveLocationIds(
                    $row['PAIS'] ?? null,
                    $row['ESTADO'] ?? null,
                    $row['CIUDAD'] ?? null,
                );

                if (!$dryRun) {
                    $this->withSavepoint(function () use ($memberId, $row, $countryId, $stateId, $cityId) {
                        Address::updateOrCreate(
                            ['member_id' => $memberId, 'is_primary' => true],
                            [
                                'street'        => $row['CALLE'] ?? null,
                                'neighborhood'  => $row['COLONIA'] ?? null,
                                'postal_code'   => $row['CODIGO_POSTAL'] ?? null,
                                'country_id'    => $countryId,
                                'state_id'      => $stateId,
                                'city_id'       => $cityId,
                                'years_in_city' => $this->parseInt($row['ANOS_RADICANDO'] ?? null),
                                'is_primary'    => true,
                            ]
                        );
                    });
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        $report->record($this->name(), $ok, $errors);
    }

    /**
     * Resuelve country_id, state_id, city_id desde textos del Excel.
     * La búsqueda es case-insensitive y sin acentos para mayor tolerancia.
     *
     * @return array{int|null, int|null, int|null}
     */
    private function resolveLocationIds(?string $paisText, ?string $estadoText, ?string $ciudadText): array
    {
        $countryId = null;
        $stateId   = null;
        $cityId    = null;

        if (!empty($paisText)) {
            $normalizedPais = $this->normalize($paisText);

            $country = Country::all()->first(function (Country $c) use ($normalizedPais) {
                $translations = is_array($c->translations) ? $c->translations : [];
                $names = array_filter([
                    $translations['es-MX'] ?? null,
                    $translations['es']    ?? null,
                    $c->name,
                ]);

                foreach ($names as $name) {
                    if ($this->normalize($name) === $normalizedPais) {
                        return true;
                    }
                }

                return false;
            });

            $countryId = $country?->id;
        }

        if (!empty($estadoText) && $countryId) {
            $normalizedEstado = $this->normalize($estadoText);

            $state = State::where('country_id', $countryId)->get()
                ->first(fn (State $s) => $this->normalize($s->name) === $normalizedEstado);

            $stateId = $state?->id;
        }

        if (!empty($ciudadText) && $stateId) {
            $normalizedCiudad = $this->normalize($ciudadText);

            $city = City::where('state_id', $stateId)->get()
                ->first(fn (City $c) => $this->normalize($c->name) === $normalizedCiudad);

            $cityId = $city?->id;
        }

        return [$countryId, $stateId, $cityId];
    }

    private function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower(
            preg_replace('/[\x{0300}-\x{036f}]/u', '', \Normalizer::normalize($value, \Normalizer::FORM_D)) ?? $value
        );
    }
}
