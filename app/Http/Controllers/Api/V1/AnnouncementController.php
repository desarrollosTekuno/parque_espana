<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminClub\Announcement;
use App\Models\Administrator\Club;

class AnnouncementController extends Controller
{
    /**
     * GET /api/v1/clubs/{club}/announcements
     *
     * Noticias/avisos publicados del club, para el carrusel de la app.
     */
    public function index(Club $club)
    {
        try {
            $announcements = Announcement::where('club_id', $club->id)
                ->where('status', 'published')
                ->active()
                ->orderByDesc('publish_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($a) => [
                    'id'         => $a->id,
                    'title'      => $a->title,
                    'content'    => $a->content,
                    'type'       => $a->type,
                    'image_url'  => $a->image_url,
                    'publish_at' => $a->publish_at,
                    'expires_at' => $a->expires_at,
                ]);

            return $this->ok($announcements);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener noticias.');
        }
    }
}
