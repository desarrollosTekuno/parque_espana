<?php

namespace Database\Seeders;

use App\Models\Administrator\Club;
use App\Models\Administrator\ClubAddress;
use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\State;
use Illuminate\Database\Seeder;

class ClubAddressSeeder extends Seeder
{
    public function run(): void
    {
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
                'street' => '25 Oriente',
                'exterior_number' => '1001',
                'interior_number' => null,
                'neighborhood' => null,
                'postal_code' => '72500',
                'city_name' => 'Puebla',
            ],
            [
                'club_code' => 'PE2',
                'street' => 'Carril a San Martinito Km. 1.5',
                'exterior_number' => null,
                'interior_number' => null,
                'neighborhood' => 'Ampliación Emiliano Zapata',
                'postal_code' => '72810',
                'city_name' => 'San Andrés Cholula',
            ],
        ];

        foreach ($addresses as $address) {
            $club = Club::where('code', $address['club_code'])->first();

            if ($club) {
                $city = null;

                if ($country && $state) {
                    $city = City::where('country_id', $country->id)
                        ->where('state_id', $state->id)
                        ->where('name', $address['city_name'])
                        ->first();
                }

                $clubAddress = ClubAddress::withTrashed()
                    ->where('club_id', $club->id)
                    ->first();

                if (!$clubAddress) {
                    $clubAddress = new ClubAddress();
                    $clubAddress->club_id = $club->id;
                }

                $clubAddress->street = $address['street'];
                $clubAddress->exterior_number = $address['exterior_number'];
                $clubAddress->interior_number = $address['interior_number'];
                $clubAddress->neighborhood = $address['neighborhood'];
                $clubAddress->postal_code = $address['postal_code'];
                $clubAddress->country_id = $country?->id;
                $clubAddress->state_id = $state?->id;
                $clubAddress->city_id = $city?->id;
                $clubAddress->deleted_at = null;
                $clubAddress->save();
            }
        }
    }
}
