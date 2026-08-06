<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;

class ClubContactInfoController extends Controller
{
    public function show(Club $club)
    {
        try {
            $club->load('clubAddress.state', 'clubAddress.city');

            return $this->ok([
                'club_name'        => $club->name,
                'email'            => $club->email,
                'phone'            => $club->phone,
                'website'          => $club->website,
                'address'          => $this->formatAddress($club),
                'logo_url'         => $club->logo_url,
                'map_image_url'    => $club->mapa_url,
                'social_whatsapp'  => $club->social_whatsapp,
                'social_instagram' => $club->social_instagram,
                'social_facebook'  => $club->social_facebook,
                'social_twitter'   => $club->social_twitter,
                'social_youtube'   => $club->social_youtube,
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener la información de contacto del club.');
        }
    }

    private function formatAddress(Club $club): ?string
    {
        $address = $club->clubAddress;
        if (!$address) {
            return null;
        }

        $streetLine = trim(implode(' ', array_filter([
            $address->street,
            $address->exterior_number,
        ])));
        if ($address->interior_number) {
            $streetLine .= " Int. {$address->interior_number}";
        }

        $cityLine = trim(implode(' ', array_filter([
            $address->postal_code,
            $address->city?->name,
        ])));

        $parts = array_filter([
            $streetLine,
            $address->neighborhood,
            $cityLine,
            $address->state?->name,
        ], fn ($part) => filled($part));

        return $parts ? implode(', ', $parts) . '.' : null;
    }
}
