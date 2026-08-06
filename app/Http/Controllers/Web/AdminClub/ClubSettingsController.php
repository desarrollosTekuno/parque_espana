<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\Administrator\ClubAddress;
use App\Models\Catalogs\Country;
use App\Rules\ExistsInSchema;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClubSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:club-settings.edit')->only('edit');
        $this->middleware('permission:club-settings.update')->only('update');
    }

    public function edit(Request $request)
    {
        $clubId = (int) ($request->club_id ?? session('club_id'));
        $club   = Club::with('clubAddress')->findOrFail($clubId);
        $address = $club->clubAddress;

        return Inertia::render('AdminClubs/Club/Edit', [
            'club' => [
                'id'               => $club->id,
                'name'             => $club->name,
                'is_active'        => (bool) $club->is_active,
                'email'            => $club->email,
                'phone'            => $club->phone,
                'website'          => $club->website,
                'logo_url'         => $club->logo_url,
                'mapa_url'         => $club->mapa_url,
                'social_whatsapp'  => $club->social_whatsapp,
                'social_instagram' => $club->social_instagram,
                'social_facebook'  => $club->social_facebook,
                'social_twitter'   => $club->social_twitter,
                'social_youtube'   => $club->social_youtube,
                'address'          => [
                    'street'           => $address->street ?? null,
                    'exterior_number'  => $address->exterior_number ?? null,
                    'interior_number'  => $address->interior_number ?? null,
                    'neighborhood'     => $address->neighborhood ?? null,
                    'postal_code'      => $address->postal_code ?? null,
                    'country_id'       => $address->country_id ?? null,
                    'state_id'         => $address->state_id ?? null,
                    'city_id'          => $address->city_id ?? null,
                ],
            ],
            'countries' => Country::select('id', 'iso2 as code', 'name', 'translations', 'demonym')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        $clubId = (int) ($request->club_id ?? session('club_id'));
        $club   = Club::findOrFail($clubId);

        $validated = $request->validate(
            [
                'name'                     => ['required', 'string', 'max:200'],
                'is_active'                => ['required'],
                'email'                    => ['nullable', 'email', 'max:255'],
                'phone'                    => ['nullable', 'string', 'max:30'],
                'website'                  => ['nullable', 'string', 'max:255'],
                'logo'                     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'mapa'                     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'social_whatsapp'          => ['nullable', 'string', 'max:200'],
                'social_instagram'         => ['nullable', 'url', 'max:500'],
                'social_facebook'          => ['nullable', 'url', 'max:500'],
                'social_twitter'           => ['nullable', 'url', 'max:500'],
                'social_youtube'           => ['nullable', 'url', 'max:500'],
                'address.street'           => ['nullable', 'string', 'max:255'],
                'address.exterior_number'  => ['nullable', 'string', 'max:30'],
                'address.interior_number'  => ['nullable', 'string', 'max:30'],
                'address.neighborhood'     => ['nullable', 'string', 'max:255'],
                'address.postal_code'      => ['nullable', 'string', 'max:10'],
                'address.country_id'       => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'address.state_id'         => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'address.city_id'          => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
            ],
            [
                'name.required'          => 'El nombre del club es obligatorio.',
                'name.max'               => 'El nombre no puede superar los 200 caracteres.',
                'logo.image'             => 'El logo debe ser una imagen.',
                'logo.mimes'             => 'El logo debe ser JPG, JPEG, PNG o WEBP.',
                'logo.max'               => 'El logo no puede superar los 2 MB.',
                'email.email'            => 'El correo no es válido.',
                'social_whatsapp.max'    => 'El WhatsApp no puede superar los 200 caracteres.',
                'social_instagram.url'   => 'El enlace de Instagram no es válido (debe iniciar con https://).',
                'social_facebook.url'    => 'El enlace de Facebook no es válido (debe iniciar con https://).',
                'social_twitter.url'     => 'El enlace de X/Twitter no es válido (debe iniciar con https://).',
                'social_youtube.url'     => 'El enlace de YouTube no es válido (debe iniciar con https://).',
                'social_instagram.max'   => 'El enlace de Instagram no puede superar los 500 caracteres.',
                'social_facebook.max'    => 'El enlace de Facebook no puede superar los 500 caracteres.',
                'social_twitter.max'     => 'El enlace de X/Twitter no puede superar los 500 caracteres.',
                'social_youtube.max'     => 'El enlace de YouTube no puede superar los 500 caracteres.',
            ]
        );

        try {
            $data = [
                'name'             => $validated['name'],
                'is_active'        => $request->boolean('is_active'),
                'email'            => $validated['email'] ?? null,
                'phone'            => $validated['phone'] ?? null,
                'website'          => $validated['website'] ?? null,
                'social_whatsapp'  => $validated['social_whatsapp'] ?? null,
                'social_instagram' => $validated['social_instagram'] ?? null,
                'social_facebook'  => $validated['social_facebook'] ?? null,
                'social_twitter'   => $validated['social_twitter'] ?? null,
                'social_youtube'   => $validated['social_youtube'] ?? null,
            ];

            if ($request->hasFile('logo')) {
                if ($club->logo_path) {
                    Storage::disk('spaces')->delete($club->logo_path);
                }

                $file     = $request->file('logo');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                Storage::disk('spaces')->putFileAs('club-logos', $file, $filename, 'public');

                $data['logo_path'] = "club-logos/{$filename}";
            } elseif ($request->boolean('remove_logo')) {
                if ($club->logo_path) {
                    Storage::disk('spaces')->delete($club->logo_path);
                }
                $data['logo_path'] = null;
            }

            if ($request->hasFile('mapa')) {
                if ($club->mapa_path) {
                    Storage::disk('spaces')->delete($club->mapa_path);
                }
                $file     = $request->file('mapa');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                Storage::disk('spaces')->putFileAs('club-mapas', $file, $filename, 'public');
                $data['mapa_path'] = "club-mapas/{$filename}";
            } elseif ($request->boolean('remove_mapa')) {
                if ($club->mapa_path) {
                    Storage::disk('spaces')->delete($club->mapa_path);
                }
                $data['mapa_path'] = null;
            }

            $club->update($data);

            $address = $validated['address'] ?? [];
            $clubAddress = ClubAddress::withTrashed()->where('club_id', $club->id)->first()
                ?? new ClubAddress(['club_id' => $club->id]);

            $clubAddress->fill([
                'street'          => $address['street'] ?? null,
                'exterior_number' => $address['exterior_number'] ?? null,
                'interior_number' => $address['interior_number'] ?? null,
                'neighborhood'    => $address['neighborhood'] ?? null,
                'postal_code'     => $address['postal_code'] ?? null,
                'country_id'      => $address['country_id'] ?? null,
                'state_id'        => $address['state_id'] ?? null,
                'city_id'         => $address['city_id'] ?? null,
            ]);
            $clubAddress->deleted_at = null;
            $clubAddress->save();

            return redirect()->back()->with('success', 'Información del club actualizada correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'No se pudo actualizar la información del club.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }
}
