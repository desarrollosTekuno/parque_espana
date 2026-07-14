<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Administrator\ClubAddress;
use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ClubAddressSeeder extends Seeder {

    public function run(): void {
        $hasClubAddressTable = Schema::hasTable('clubs.club_addresses');
        $country = Country::where('iso2', 'MX')->first();
        $state = null;

        if ($country) {
            $state = State::where('country_id', $country->id)
                ->where('name', 'Puebla')
                ->first();
        }

        $addresses = [
            [
                'club_code' => 'PE1',
                'street' => '25 Oriente #1001',
                'neighborhood' => null,
                'postal_code' => '72500',
                'city_name' => 'Puebla',
                'full_address' => '25 Oriente #1001, C.P. 72500, Puebla, Pue. México',
            ],
            [
                'club_code' => 'PE2',
                'street' => 'Carril a San Martinito Km. 1.5',
                'neighborhood' => 'Ampliación Emiliano Zapata',
                'postal_code' => '72810',
                'city_name' => 'San Andrés Cholula',
                'full_address' => 'Carril a San Martinito Km. 1.5, Col. Ampliación Emiliano Zapata, San Andrés Cholula, Puebla. C.P. 72810',
            ],
        ];

        foreach ($addresses as $address) {
            $club = Club::where('code', $address['club_code'])->first();

            if ($club) {
                $club->update([
                    'address' => $address['full_address'],
                ]);

                if ($hasClubAddressTable) {
                    $city = null;

                    if ($country && $state) {
                        $city = City::where('country_id', $country->id)
                            ->where('state_id', $state->id)
                            ->where('name', $address['city_name'])
                            ->first();
                    }

                    $clubAddress = ClubAddress::withTrashed()->updateOrCreate(
                        ['club_id' => $club->id],
                        [
                            'street' => $address['street'],
                            'neighborhood' => $address['neighborhood'],
                            'postal_code' => $address['postal_code'],
                            'country_id' => $country?->id,
                            'state_id' => $state?->id,
                            'city_id' => $city?->id,
                            'deleted_at' => null,
                        ]
                    );

                    $clubAddress->restore();
                }
            }
        }
    }
}
