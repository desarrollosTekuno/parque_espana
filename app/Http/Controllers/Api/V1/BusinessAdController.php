<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\BusinessAd;
use App\Models\AdminClub\BusinessCategory;
use App\Models\Administrator\Club;
use App\Models\Members\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessAdController extends Controller
{
    public function index(Request $request, Club $club)
    {
        try {
            $query = BusinessAd::with('category')
                ->where('club_id', $club->id)
                ->where('status_id', 5)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                });

            if ($request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            $ads = $query->orderBy('published_at', 'desc')
                ->get()
                ->map(fn ($ad) => $this->transformAd($ad));

            return $this->ok($ads);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener anuncios.');
        }
    }

    public function show(Club $club, BusinessAd $businessAd)
    {
        try {
            $businessAd->load('category');

            if ($businessAd->club_id !== $club->id) {
                return $this->notFound('El anuncio no pertenece a este club.');
            }

            if ($businessAd->status_id !== 5) {
                return $this->notFound('El anuncio no está publicado.');
            }

            if ($businessAd->expires_at && $businessAd->expires_at->lt(now())) {
                return $this->notFound('El anuncio ya expiró.');
            }

            return $this->ok($this->transformAd($businessAd));
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener anuncio.');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_id'   => 'required',
                'club_id'     => 'required',
                'name'        => 'required|string|max:255',
                'category_id' => 'required|integer',
                'image'       => 'nullable|image|max:2048',
                'description' => 'nullable|string',
                'address'     => 'nullable|string|max:255',
                'phone'       => 'nullable|string|max:20',
                'email'       => 'nullable|email|max:255',
                'website'     => 'nullable|string|max:255',
            ]);

            $member = Member::find($request->member_id);
            if (!$member) {
                return $this->unprocessable('El usuario no existe.');
            }

            $club = Club::find($request->club_id);
            if (!$club) {
                return $this->unprocessable('El club no existe.');
            }

            $category = BusinessCategory::where('id', $request->category_id)
                ->where('club_id', $request->club_id)
                ->where('is_active', true)
                ->first();

            if (!$category) {
                return $this->unprocessable('La categoría no existe para este club.');
            }

            $exists = BusinessAd::where('member_id', $request->member_id)
                ->where('club_id', $request->club_id)
                ->where('name', $request->name)
                ->exists();

            if ($exists) {
                return $this->conflict('Ya existe un anuncio con este nombre para este usuario en este club.');
            }

            if ($request->hasFile('image')) {
                $path              = $request->file('image')->store('business_ads', 'public');
                $validated['image'] = Storage::url($path);
            }

            $ad = BusinessAd::create([
                ...$validated,
                'status_id' => 1,
            ]);

            return $this->created('Anuncio creado correctamente.', $ad->load('category'));
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al crear anuncio.');
        }
    }

    private function transformAd(BusinessAd $ad): array
    {
        return [
            'id'           => $ad->id,
            'name'         => $ad->name,
            'category'     => $ad->category ? [
                'id'   => $ad->category->id,
                'name' => $ad->category->name,
            ] : null,
            'image_url'    => $ad->image,
            'description'  => $ad->description,
            'address'      => $ad->address,
            'phone'        => $ad->phone,
            'email'        => $ad->email,
            'website'      => $ad->website,
            'published_at' => $ad->published_at,
            'expires_at'   => $ad->expires_at,
        ];
    }
}
