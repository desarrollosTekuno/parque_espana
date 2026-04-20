<?php

namespace App\Console\Commands;

use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\State;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportLocationCatalogs extends Command
{
    protected ?array $demonymMap = null;

    // How to use:
    /* 
    download file from repository and save into folder, then run command php artisan catalogs:import-locations --source=dr5hn --path="C:\ruta\a\repo" --include-cities
    */
    protected $signature = 'catalogs:import-locations
        {--source=dr5hn : Import source: dr5hn or api}
        {--path= : Local path to dr5hn repo root or json directory}
        {--country=* : ISO2 codes to import, e.g. MX US CA}
        {--include-cities : Import cities too}
        {--fresh : Clear countries, states, cities before import}';

    protected $description = 'Import countries, states, and cities into local catalogs tables';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Fresh import: clearing catalogs.cities, catalogs.states, catalogs.countries.');

            DB::transaction(function () {
                City::query()->delete();
                State::query()->delete();
                Country::query()->delete();
            });
        }

        $requestedCountries = collect((array) $this->option('country'))
            ->map(fn (string $iso2) => Str::upper(trim($iso2)))
            ->filter()
            ->unique()
            ->values();

        $source = Str::lower((string) $this->option('source'));

        return match ($source) {
            'dr5hn' => $this->importFromDr5hn(
                requestedCountries: $requestedCountries,
                includeCities: (bool) $this->option('include-cities')
            ),
            'api' => $this->importFromApi(
                requestedCountries: $requestedCountries,
                includeCities: (bool) $this->option('include-cities')
            ),
            default => $this->failWithMessage("Unsupported source [$source]. Use dr5hn or api."),
        };
    }

    protected function request(string $path): array
    {
        $apiKey = (string) config('services.country_state_city.api_key');
        $baseUrl = rtrim((string) config('services.country_state_city.base_url'), '/');

        if ($apiKey === '') {
            throw new \RuntimeException('Missing COUNTRY_STATE_CITY_API_KEY.');
        }

        if ($baseUrl === '') {
            throw new \RuntimeException('Missing Country State City base URL.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.country_state_city.base_url'), '/'))
            ->acceptJson()
            ->withHeaders([
                'X-CSCAPI-KEY' => (string) config('services.country_state_city.api_key'),
            ])
            ->timeout(60)
            ->get($path);

        $response->throw();

        return $response->json();
    }

    protected function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function importFromApi($requestedCountries, bool $includeCities): int
    {
        $countries = collect($this->request('/countries'));

        if ($requestedCountries->isNotEmpty()) {
            $countries = $countries->whereIn('iso2', $requestedCountries)->values();
        }

        if ($countries->isEmpty()) {
            $this->warn('No countries to import.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Importing %d countries from API.', $countries->count()));

        $countries->each(function (array $countryData) use ($includeCities) {
            $country = $this->upsertCountry($countryData);

            $this->line("Country: {$country->iso2} - {$country->name}");

            $states = collect($this->request("/countries/{$country->iso2}/states"));

            $states->each(function (array $stateData) use ($country, $includeCities) {
                $stateDetails = $stateData;

                if (!empty($stateData['iso2'])) {
                    $stateDetails = $this->request("/countries/{$country->iso2}/states/{$stateData['iso2']}");
                }

                $state = $this->upsertState($country, [
                    'id' => $stateDetails['id'] ?? $stateData['id'] ?? null,
                    'name' => $stateDetails['name'] ?? $stateData['name'],
                    'iso2' => $stateData['iso2'] ?? null,
                    'type' => $stateDetails['type'] ?? null,
                    'latitude' => $stateDetails['latitude'] ?? null,
                    'longitude' => $stateDetails['longitude'] ?? null,
                ]);

                if (!$includeCities || empty($state->iso2)) {
                    return;
                }

                collect($this->request("/countries/{$country->iso2}/states/{$state->iso2}/cities"))
                    ->each(fn (array $cityData) => $this->upsertCity($country, $state, $cityData));
            });

            if ($includeCities && $states->isEmpty()) {
                collect($this->request("/countries/{$country->iso2}/cities"))
                    ->each(fn (array $cityData) => $this->upsertCity($country, null, $cityData));
            }
        });

        $this->info('Location catalogs import finished.');

        return self::SUCCESS;
    }

    protected function importFromDr5hn($requestedCountries, bool $includeCities): int
    {
        $jsonPath = $this->resolveDr5hnJsonPath();

        if (!is_dir($jsonPath)) {
            return $this->failWithMessage("Path [$jsonPath] not found. Pass --path to dr5hn repo root or json directory.");
        }

        if ($includeCities && is_file($jsonPath . DIRECTORY_SEPARATOR . 'countries+states+cities.json')) {
            return $this->importFromDr5hnCombined(
                jsonPath: $jsonPath,
                requestedCountries: $requestedCountries
            );
        }

        $countries = collect($this->loadJsonFile($jsonPath . DIRECTORY_SEPARATOR . 'countries.json'));
        $states = collect($this->loadJsonFile($jsonPath . DIRECTORY_SEPARATOR . 'states.json'));
        $cities = $includeCities
            ? collect($this->loadJsonFile($jsonPath . DIRECTORY_SEPARATOR . 'cities.json'))
            : collect();

        if ($requestedCountries->isNotEmpty()) {
            $countries = $countries->whereIn('iso2', $requestedCountries)->values();
        }

        if ($countries->isEmpty()) {
            $this->warn('No countries to import.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Importing %d countries from dr5hn dataset.', $countries->count()));

        $countryIdMap = [];

        $countries->each(function (array $countryData) use (&$countryIdMap) {
            $country = $this->upsertCountry($countryData);
            $countryIdMap[(string) ($countryData['id'] ?? '')] = $country->id;
            $countryIdMap[(string) ($countryData['iso2'] ?? '')] = $country->id;

            $this->line("Country: {$country->iso2} - {$country->name}");
        });

        $states = $states->filter(function (array $stateData) use ($countryIdMap) {
            $countryId = (string) ($stateData['country_id'] ?? '');
            $countryCode = (string) ($stateData['country_code'] ?? '');

            return isset($countryIdMap[$countryId]) || isset($countryIdMap[$countryCode]);
        })->values();

        $stateIdMap = [];

        $states->each(function (array $stateData) use ($countryIdMap, &$stateIdMap) {
            $country = Country::find(
                $countryIdMap[(string) ($stateData['country_id'] ?? '')]
                ?? $countryIdMap[(string) ($stateData['country_code'] ?? '')]
                ?? null
            );

            if (!$country) {
                return;
            }

            $state = $this->upsertState($country, [
                'id' => $stateData['id'] ?? null,
                'name' => $stateData['name'],
                'iso2' => $stateData['state_code'] ?? $stateData['iso2'] ?? null,
                'type' => $stateData['type'] ?? null,
                'latitude' => $stateData['latitude'] ?? null,
                'longitude' => $stateData['longitude'] ?? null,
            ]);

            $stateIdMap[(string) ($stateData['id'] ?? '')] = $state->id;
            $stateIdMap[$country->id . '|' . (string) ($stateData['state_code'] ?? $stateData['iso2'] ?? '')] = $state->id;
        });

        if ($includeCities) {
            $cities->filter(function (array $cityData) use ($countryIdMap) {
                $countryId = (string) ($cityData['country_id'] ?? '');
                $countryCode = (string) ($cityData['country_code'] ?? '');

                return isset($countryIdMap[$countryId]) || isset($countryIdMap[$countryCode]);
            })->each(function (array $cityData) use ($countryIdMap, $stateIdMap) {
                $country = Country::find(
                    $countryIdMap[(string) ($cityData['country_id'] ?? '')]
                    ?? $countryIdMap[(string) ($cityData['country_code'] ?? '')]
                    ?? null
                );

                if (!$country) {
                    return;
                }

                $state = null;
                $stateExternalId = (string) ($cityData['state_id'] ?? '');
                $stateCode = (string) ($cityData['state_code'] ?? '');

                if (isset($stateIdMap[$stateExternalId])) {
                    $state = State::find($stateIdMap[$stateExternalId]);
                } elseif ($stateCode !== '' && isset($stateIdMap[$country->id . '|' . $stateCode])) {
                    $state = State::find($stateIdMap[$country->id . '|' . $stateCode]);
                }

                $this->upsertCity($country, $state, $cityData);
            });
        }

        $this->info('Location catalogs import finished.');

        return self::SUCCESS;
    }

    protected function importFromDr5hnCombined(string $jsonPath, $requestedCountries): int
    {
        $countries = collect(
            $this->loadJsonFile($jsonPath . DIRECTORY_SEPARATOR . 'countries+states+cities.json')
        );

        if ($requestedCountries->isNotEmpty()) {
            $countries = $countries->whereIn('iso2', $requestedCountries)->values();
        }

        if ($countries->isEmpty()) {
            $this->warn('No countries to import.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Importing %d countries from dr5hn combined dataset.', $countries->count()));

        $countries->each(function (array $countryData) {
            $country = $this->upsertCountry($countryData);

            $this->line("Country: {$country->iso2} - {$country->name}");

            collect($countryData['states'] ?? [])->each(function (array $stateData) use ($country) {
                $state = $this->upsertState($country, [
                    'id' => $stateData['id'] ?? null,
                    'name' => $stateData['name'],
                    'iso2' => $stateData['state_code'] ?? $stateData['iso2'] ?? null,
                    'type' => $stateData['type'] ?? null,
                    'latitude' => $stateData['latitude'] ?? null,
                    'longitude' => $stateData['longitude'] ?? null,
                ]);

                collect($stateData['cities'] ?? [])->each(
                    fn (array $cityData) => $this->upsertCity($country, $state, $cityData)
                );
            });
        });

        $this->info('Location catalogs import finished.');

        return self::SUCCESS;
    }

    protected function resolveDr5hnJsonPath(): string
    {
        $path = (string) $this->option('path');

        if ($path === '') {
            $path = storage_path('app/imports/dr5hn-countries-states-cities-database');
        }

        $normalized = rtrim($path, DIRECTORY_SEPARATOR);

        if (is_dir($normalized . DIRECTORY_SEPARATOR . 'json')) {
            return $normalized . DIRECTORY_SEPARATOR . 'json';
        }

        return $normalized;
    }

    protected function loadJsonFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("File [$path] not found.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException("File [$path] does not contain valid JSON array data.");
        }

        return $decoded;
    }

    protected function upsertCountry(array $countryData): Country
    {
        $country = null;

        if (!empty($countryData['id'])) {
            $country = Country::query()->where('external_id', $countryData['id'])->first();
        }

        if (!$country) {
            $country = Country::query()->where('iso2', $countryData['iso2'])->first();
        }

        if (!$country) {
            $country = new Country();
        }

        $country->fill([
            'external_id' => $countryData['id'] ?? null,
            'name' => $countryData['name'],
            'translations' => $this->normalizeTranslations($countryData['translations'] ?? null),
            'iso2' => $countryData['iso2'],
            'iso3' => $countryData['iso3'] ?? null,
            'numeric_code' => $countryData['numeric_code'] ?? null,
            'phonecode' => $countryData['phonecode'] ?? null,
            'capital' => $countryData['capital'] ?? null,
            'currency' => $countryData['currency'] ?? null,
            'currency_name' => $countryData['currency_name'] ?? null,
            'currency_symbol' => $countryData['currency_symbol'] ?? null,
            'native' => $countryData['native'] ?? null,
            'region' => $countryData['region'] ?? null,
            'subregion' => $countryData['subregion'] ?? null,
            'nationality' => $countryData['nationality'] ?? null,
            'demonym' => $this->resolveCountryDemonym($countryData['iso2'] ?? null)
                ?? $country->demonym
                ?? ($countryData['demonym'] ?? $countryData['nationality'] ?? null),
            'emoji' => $countryData['emoji'] ?? null,
        ]);
        $country->save();

        return $country;
    }

    protected function upsertState(Country $country, array $stateData): State
    {
        $state = null;

        if (!empty($stateData['id'])) {
            $state = State::query()->where('external_id', $stateData['id'])->first();
        }

        if (!$state) {
            $state = State::query()
                ->where('country_id', $country->id)
                ->where('name', $stateData['name'])
                ->first();
        }

        if (!$state && !empty($stateData['iso2'])) {
            $state = State::query()
                ->where('country_id', $country->id)
                ->where('iso2', $stateData['iso2'])
                ->first();
        }

        if (!$state) {
            $state = new State();
        }

        $state->fill([
            'country_id' => $country->id,
            'external_id' => $stateData['id'] ?? null,
            'name' => $stateData['name'],
            'iso2' => $stateData['iso2'] ?? null,
            'type' => $stateData['type'] ?? null,
            'latitude' => $this->toDecimal($stateData['latitude'] ?? null),
            'longitude' => $this->toDecimal($stateData['longitude'] ?? null),
        ]);
        $state->save();

        return $state;
    }

    protected function upsertCity(Country $country, ?State $state, array $cityData): City
    {
        $city = null;

        if (!empty($cityData['id'])) {
            $city = City::query()->where('external_id', $cityData['id'])->first();
        }

        if (!$city) {
            $city = City::query()
                ->where('country_id', $country->id)
                ->where('state_id', $state?->id)
                ->where('name', $cityData['name'])
                ->first();
        }

        if (!$city) {
            $city = new City();
        }

        $city->fill([
            'country_id' => $country->id,
            'state_id' => $state?->id,
            'external_id' => $cityData['id'] ?? null,
            'name' => $cityData['name'],
            'latitude' => $this->toDecimal($cityData['latitude'] ?? null),
            'longitude' => $this->toDecimal($cityData['longitude'] ?? null),
        ]);
        $city->save();

        return $city;
    }

    protected function failWithMessage(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }

    protected function resolveCountryDemonym(?string $iso2): ?string
    {
        if (!$iso2) {
            return null;
        }

        $map = $this->getDemonymMap();

        return $map[Str::upper($iso2)] ?? null;
    }

    protected function getDemonymMap(): array
    {
        if ($this->demonymMap !== null) {
            return $this->demonymMap;
        }

        $path = database_path('data/nationality_demonyms.json');

        if (!is_file($path)) {
            return $this->demonymMap = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return $this->demonymMap = [];
        }

        return $this->demonymMap = collect($decoded)
            ->mapWithKeys(fn ($demonym, $code) => [Str::upper((string) $code) => $demonym])
            ->all();
    }

    protected function normalizeTranslations(mixed $translations): ?array
    {
        if (!is_array($translations)) {
            return null;
        }

        return collect($translations)
            ->filter(fn ($value, $key) => is_string($key) && $key !== '' && is_string($value) && trim($value) !== '')
            ->mapWithKeys(fn (string $value, string $key) => [trim($key) => trim($value)])
            ->all();
    }
}
