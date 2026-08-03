<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Website\CarouselImage;

class WebsiteCarouselController extends Controller {

    public function index(Club $club) {
        try {
            $images = CarouselImage::where('club_id', $club->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($image) => [
                    'id' => $image->id,
                    'description' => $image->description,
                    'image_url' => $image->image_url,
                ]);

            return $this->ok($images);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener el carrusel.');
        }
    }
}
