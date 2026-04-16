<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\AdminClub\BusinessAd;
use Illuminate\Support\Facades\Auth;
use App\Models\Administrator\Club;
use App\Models\Members\Member;

class BusinessAdController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'member_id' => 'required',
                'club_id' => 'required',
                'name' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
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
                    'message' => 'El socio no existe'
                ], 422);
            }

            $club = Club::find($request->club_id);
            if (!$club) {
                return response()->json([
                    'success' => false,
                    'message' => 'El club no existe'
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
                    'message' => 'Ya existe un anuncio con este nombre para este socio en este club'
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
                'data' => $ad
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
