<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\BusinessCategory;
use App\Models\Administrator\Club;

class BusinessCategoryController extends Controller
{
    public function index(Club $club)
    {
        try {
            $categories = BusinessCategory::where('club_id', $club->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($category) => [
                    'id'        => $category->id,
                    'name'      => $category->name,
                    'image_url' => $category->image_url,
                ]);

            return $this->ok($categories);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener categorías.');
        }
    }
}
