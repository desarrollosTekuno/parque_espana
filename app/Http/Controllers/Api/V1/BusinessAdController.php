<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\AdminClub\BusinessAd;


class BusinessAdController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

     public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'member_id' => 'required|exists:members.members,id',
                'club_id' => 'required|exists:clubs.clubs,id',
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

            $data = $validator->validated();
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('business_ads', 'public');
                $data['image'] = Storage::url($path);
            }
            $ad = BusinessAd::create([
                ...$data,
                'status_id' => 1 // pendiente
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
