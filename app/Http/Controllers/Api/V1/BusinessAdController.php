<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\AdminClub\BusinessAd;
use App\Models\AdminClub\BusinessCategory;
use App\Models\Administrator\Club;
use App\Models\Members\Member;

class BusinessAdController extends Controller {

    private function transformAd(BusinessAd $ad): array
    {
        return [
            'id' => $ad->id,
            'name' => $ad->name,
            'category' => $ad->category ? [
                'id' => $ad->category->id,
                'name' => $ad->category->name,
            ] : null,
            'image_url' => $ad->image,
            'description' => $ad->description,
            'address' => $ad->address,
            'phone' => $ad->phone,
            'email' => $ad->email,
            'website' => $ad->website,
            'published_at' => $ad->published_at,
            'expires_at' => $ad->expires_at,
        ];
    }

    public function index(Request $request, Club $club) {
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

            return response()->json([
                'success' => true,
                'message' => 'Anuncios obtenidos correctamente',
                'data' => $ads
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener anuncios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Club $club, BusinessAd $businessAd)
    {
        try {
            $businessAd->load('category');

            if ($businessAd->club_id !== $club->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El anuncio no pertenece a este club'
                ], 404);
            }

            if ($businessAd->status_id !== 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'El anuncio no está publicado'
                ], 404);
            }

            if ($businessAd->expires_at && $businessAd->expires_at->lt(now())) {
                return response()->json([
                    'success' => false,
                    'message' => 'El anuncio ya expiró'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Anuncio obtenido correctamente',
                'data' => $this->transformAd($businessAd)
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener anuncio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request) {
        try {

            $validator = Validator::make($request->all(), [
                'member_id' => 'required',
                'club_id' => 'required',
                'name' => 'required|string|max:255',
                'category_id' => 'required|integer',
                'image' => 'nullable|image|max:2048',
                'description' => 'nullable|string',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'website' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $member = Member::find($request->member_id);
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no existe'
                ], 422);
            }

            $club = Club::find($request->club_id);
            if (!$club) {
                return response()->json([
                    'success' => false,
                    'message' => 'El club no existe'
                ], 422);
            }

            $category = BusinessCategory::where('id', $request->category_id)
                ->where('club_id', $request->club_id)
                ->where('is_active', true)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'La categoría no existe para este club'
                ], 422);
            }

            $data = $validator->validated();

            $exists = BusinessAd::where('member_id', $request->member_id)
                ->where('club_id', $request->club_id)
                ->where('name', $request->name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un anuncio con este nombre para este usuario en este club'
                ], 422);
            }

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('business_ads', 'public');
                $data['image'] = Storage::url($path);
            }

            $ad = BusinessAd::create([
                ...$data,
                'status_id' => 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anuncio creado correctamente',
                'data' => $ad->load('category')
            ], 201);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear anuncio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
