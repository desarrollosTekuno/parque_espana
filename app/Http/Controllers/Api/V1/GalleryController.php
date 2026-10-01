<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GalleryController extends Controller
{
    /**
     * GET /api/v1/clubs/{club}/gallery/albums
     *
     * Lista de albumes (photosets) de Flickr del club, para el modulo de Eventos/Galeria de la app.
     */
    public function albums(Request $request, Club $club)
    {
        try {
            $credentials = config("services.flickr.{$club->code}");
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 15);

            $response = Http::get(config('services.flickr.base_url'), [
                'method' => 'flickr.photosets.getList',
                'api_key' => $credentials['api_key'],
                'user_id' => $credentials['user_id'],
                'primary_photo_extras' => 'url_m',
                'page' => $page,
                'per_page' => $perPage,
                'format' => 'json',
                'nojsoncallback' => 1,
            ]);

            $photosets = $response->json('photosets', []);

            $albums = collect($photosets['photoset'] ?? [])->map(fn ($photoset) => [
                'id' => $photoset['id'],
                'title' => $photoset['title']['_content'] ?? '',
                'photo_count' => $photoset['photos'] ?? 0,
                'primary_photo_url' => $photoset['primary_photo_extras']['url_m'] ?? null,
            ]);

            return $this->ok([
                'albums' => $albums,
                'page' => $photosets['page'] ?? $page,
                'total_pages' => $photosets['pages'] ?? 1,
                'total' => $photosets['total'] ?? $albums->count(),
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener las galerias.');
        }
    }

    /**
     * GET /api/v1/clubs/{club}/gallery/albums/{photosetId}
     *
     * Fotos de un album (photoset) de Flickr, para el detalle de la galeria en la app.
     */
    public function photos(Request $request, Club $club, string $photosetId)
    {
        try {
            $credentials = config("services.flickr.{$club->code}");
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);

            $response = Http::get(config('services.flickr.base_url'), [
                'method' => 'flickr.photosets.getPhotos',
                'api_key' => $credentials['api_key'],
                'user_id' => $credentials['user_id'],
                'photoset_id' => $photosetId,
                'extras' => 'url_m,url_l',
                'privacy_filter' => 1,
                'media' => 'photos',
                'page' => $page,
                'per_page' => $perPage,
                'format' => 'json',
                'nojsoncallback' => 1,
            ]);

            $photoset = $response->json('photoset', []);

            $photos = collect($photoset['photo'] ?? [])->map(fn ($photo) => [
                'id' => $photo['id'],
                'title' => $photo['title'] ?? '',
                'url' => $photo['url_m'] ?? $photo['url_l'] ?? null,
                'url_large' => $photo['url_l'] ?? $photo['url_m'] ?? null,
            ]);

            return $this->ok([
                'album_title' => $photoset['title'] ?? '',
                'photos' => $photos,
                'page' => $photoset['page'] ?? $page,
                'total_pages' => $photoset['pages'] ?? 1,
                'total' => $photoset['total'] ?? $photos->count(),
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener las fotos del album.');
        }
    }
}
