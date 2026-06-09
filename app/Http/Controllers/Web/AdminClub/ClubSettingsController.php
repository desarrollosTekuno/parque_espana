<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
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
        $club   = Club::findOrFail($clubId);

        return Inertia::render('AdminClubs/Club/Edit', [
            'club' => [
                'id'               => $club->id,
                'name'             => $club->name,
                'address'          => $club->address,
                'is_active'        => (bool) $club->is_active,
                'logo_url'         => $club->logo_url,
                'social_whatsapp'  => $club->social_whatsapp,
                'social_instagram' => $club->social_instagram,
                'social_facebook'  => $club->social_facebook,
                'social_youtube'   => $club->social_youtube,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $clubId = (int) ($request->club_id ?? session('club_id'));
        $club   = Club::findOrFail($clubId);

        $validated = $request->validate(
            [
                'name'             => ['required', 'string', 'max:200'],
                'address'          => ['nullable', 'string', 'max:500'],
                'is_active'        => ['required'],
                'logo'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'social_whatsapp'  => ['nullable', 'string', 'max:200'],
                'social_instagram' => ['nullable', 'url', 'max:500'],
                'social_facebook'  => ['nullable', 'url', 'max:500'],
                'social_youtube'   => ['nullable', 'url', 'max:500'],
            ],
            [
                'name.required'          => 'El nombre del club es obligatorio.',
                'name.max'               => 'El nombre no puede superar los 200 caracteres.',
                'address.max'            => 'La dirección no puede superar los 500 caracteres.',
                'logo.image'             => 'El logo debe ser una imagen.',
                'logo.mimes'             => 'El logo debe ser JPG, JPEG, PNG o WEBP.',
                'logo.max'               => 'El logo no puede superar los 2 MB.',
                'social_whatsapp.max'    => 'El WhatsApp no puede superar los 200 caracteres.',
                'social_instagram.url'   => 'El enlace de Instagram no es válido (debe iniciar con https://).',
                'social_facebook.url'    => 'El enlace de Facebook no es válido (debe iniciar con https://).',
                'social_youtube.url'     => 'El enlace de YouTube no es válido (debe iniciar con https://).',
                'social_instagram.max'   => 'El enlace de Instagram no puede superar los 500 caracteres.',
                'social_facebook.max'    => 'El enlace de Facebook no puede superar los 500 caracteres.',
                'social_youtube.max'     => 'El enlace de YouTube no puede superar los 500 caracteres.',
            ]
        );

        try {
            $data = [
                'name'             => $validated['name'],
                'address'          => $validated['address'] ?? null,
                'is_active'        => $request->boolean('is_active'),
                'social_whatsapp'  => $validated['social_whatsapp'] ?? null,
                'social_instagram' => $validated['social_instagram'] ?? null,
                'social_facebook'  => $validated['social_facebook'] ?? null,
                'social_youtube'   => $validated['social_youtube'] ?? null,
            ];

            if ($request->hasFile('logo')) {
                // Eliminar logo anterior si existe
                if ($club->logo_path) {
                    Storage::disk('spaces')->delete($club->logo_path);
                }

                // Subir nuevo logo con visibilidad pública
                $file     = $request->file('logo');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                Storage::disk('spaces')->putFileAs('club-logos', $file, $filename, 'public');

                $data['logo_path'] = "club-logos/{$filename}";
            }

            $club->update($data);

            return redirect()->back()->with('success', 'Información del club actualizada correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'No se pudo actualizar la información del club.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }
}
