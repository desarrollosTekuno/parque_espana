<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipType;
use App\Models\Website\CarouselImage;
use App\Models\Website\HomeCard;
use App\Models\Website\VirtualTourCategory;
use App\Models\Website\WebsiteEvent;
use Illuminate\Http\Request;

class WebsiteContentController extends Controller
{
    private const EVENT_TYPES = [
        'activity' => ['label' => 'Actividad', 'color' => '#0097A7'],
        'celebration' => ['label' => 'Celebración', 'color' => '#EC659C'],
        'holiday' => ['label' => 'Día festivo', 'color' => '#F4B400'],
    ];

    public function carousel(Club $club)
    {
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

    public function homeCards(Club $club)
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

    public function membershipPrices(Club $club)
    {
        try {
            $year = now()->year;

            $prices = MembershipType::where('club_id', $club->id)
                ->where('show_in_listing', true)
                ->with(['pricingRules' => function ($query) {
                    $query->where('is_active', true)
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('valid_from')
                                ->orWhereDate('valid_from', '<=', today());
                        })
                        ->where(function ($dateQuery) {
                            $dateQuery->whereNull('valid_until')
                                ->orWhereDate('valid_until', '>=', today());
                        })
                        ->orderBy('priority')
                        ->orderBy('id');
                }])
                ->orderBy('name')
                ->get()
                ->map(function ($membershipType) use ($year) {
                    $rule = $membershipType->pricingRules->first();

                    return [
                        'id' => $membershipType->id,
                        'code' => $membershipType->code,
                        'name' => $membershipType->name,
                        'monthly_fee' => $rule?->resolveMonthlyFee($year),
                        'inscription_fee' => $rule?->resolveInscriptionFee($year),
                        'currency' => 'MXN',
                        'year' => $year,
                    ];
                });

            return $this->ok($prices);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener los precios de membresías.');
        }
    }

    public function virtualTour(Club $club)
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

    public function events(Request $request, Club $club)
    {
        try {
            $query = WebsiteEvent::where('club_id', $club->id);

            if ($request->filled('year')) {
                $query->whereYear('event_date', (int) $request->year);
            }

            if ($request->filled('month')) {
                $query->whereMonth('event_date', (int) $request->month);
            }

            $events = $query->orderBy('event_date')
                ->orderBy('id')
                ->get()
                ->map(function ($event) {
                    $type = self::EVENT_TYPES[$event->type];

                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'date' => $event->event_date->format('Y-m-d'),
                        'type' => $event->type,
                        'type_label' => $type['label'],
                        'color' => $type['color'],
                    ];
                });

            return $this->ok($events);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener los eventos.');
        }
    }
}
