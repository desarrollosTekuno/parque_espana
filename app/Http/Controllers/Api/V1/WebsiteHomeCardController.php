<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Website\HomeCard;

class WebsiteHomeCardController extends Controller
{
    public function index(Club $club)
    {
        try {
            $cards = HomeCard::where('club_id', $club->id)
                ->orderBy('category')
                ->orderBy('id')
                ->get()
                ->map(fn ($card) => [
                    'id' => $card->id,
                    'category' => $card->category,
                    'image_url' => $card->image_url,
                ]);

            return $this->ok($cards);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener las cards de inicio.');
        }
    }
}
