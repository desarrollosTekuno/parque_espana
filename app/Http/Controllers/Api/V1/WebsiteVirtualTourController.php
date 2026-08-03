<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Website\VirtualTourCategory;

class WebsiteVirtualTourController extends Controller
{
    public function index(Club $club)
    {
        try {
            $categories = VirtualTourCategory::where('club_id', $club->id)
                ->with('images')
                ->orderBy('id')
                ->get()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'images' => $category->images->map(fn ($image) => [
                        'id' => $image->id,
                        'title' => $image->title,
                        'image_url' => $image->image_url,
                    ]),
                ]);

            return $this->ok($categories);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener la vista virtual.');
        }
    }
}
