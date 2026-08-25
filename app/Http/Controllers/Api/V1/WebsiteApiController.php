<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Models\Website\CarouselImage;
use App\Models\Website\HomeCard;
use App\Models\Website\VirtualTourCategory;
use App\Models\Website\WebsiteEvent;
use Illuminate\Http\Request;

class WebsiteApiController extends Controller {
    private const EVENT_TYPES = [
        'summer_course' => ['label' => 'Curso de verano', 'color' => '#5AA2B8'],
        'pilgrimage' => ['label' => 'Romería', 'color' => '#D676A5'],
        'holidays' => ['label' => 'Días festivos', 'color' => '#E8B72C'],
    ];

    public function carousel(Club $club) {
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

    public function homeCards(Club $club) {
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

    public function membershipPrices(Club $club) {
        try {
            $year = now()->year;
            $prices = [];

            $familyType = MembershipType::where('club_id', $club->id)
                ->where('allows_multiple_members', true)
                ->orderBy('id')
                ->first();

            $familyRule = PricingRule::where('membership_type_id', $familyType->id)
                ->where('requires_multiple_clubs', false)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($familyRule) {
                $prices[] = [
                    'id' => $familyType->id,
                    'code' => $familyType->code,
                    'name' => 'Familiar',
                    'monthly_payment' => $familyRule->resolveMonthlyFee($year),
                    'inscription_payment' => $familyRule->resolveInscriptionFee($year),
                    'year' => $year,
                ];
            }

            $individualTransition = PricingRule::where('from_membership_type_id', $familyType->id)
                ->where('requires_origin_family', false)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($individualTransition) {
                $individualType = MembershipType::find($individualTransition->membership_type_id);
                $individualRule = PricingRule::where('membership_type_id', $individualType->id)
                    ->where('requires_multiple_clubs', false)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();

                if ($individualRule) {
                    $prices[] = [
                        'id' => $individualType->id,
                        'code' => $individualType->code,
                        'name' => 'Individual',
                        'monthly_payment' => $individualRule->resolveMonthlyFee($year),
                        'inscription_payment' => $individualRule->resolveInscriptionFee($year),
                        'year' => $year,
                    ];
                }
            }

            $solidariaTransition = PricingRule::where('from_membership_type_id', $familyType->id)
                ->where('requires_origin_family', true)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($solidariaTransition) {
                $solidariaType = MembershipType::find($solidariaTransition->membership_type_id);
                $solidariaRule = PricingRule::where('membership_type_id', $solidariaType->id)
                    ->where('requires_multiple_clubs', false)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();

                if ($solidariaRule) {
                    $prices[] = [
                        'id' => $solidariaType->id,
                        'code' => $solidariaType->code,
                        'name' => 'Solidaria',
                        'monthly_payment' => $solidariaRule->resolveMonthlyFee($year),
                        'inscription_payment' => $solidariaRule->resolveInscriptionFee($year),
                        'year' => $year,
                    ];
                }
            }

            return $this->ok($prices);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener los precios de membresías.');
        }
    }

    public function virtualTour(Club $club) {
        try {
            $virtualTourConfig = config("website.virtual_tour.{$club->code}", []);
            $savedCategories = VirtualTourCategory::where('club_id', $club->id)
                ->with('images')
                ->get()
                ->keyBy('name');

            $categories = collect($virtualTourConfig)->map(function ($titles, $categoryName) use ($savedCategories) {
                $category = $savedCategories->get($categoryName);

                return [
                    'id' => $category?->id,
                    'name' => $categoryName,
                    'images' => collect($titles)->map(function ($title) use ($category) {
                        $image = $category?->images->firstWhere('title', $title);

                        return [
                            'id' => $image?->id,
                            'title' => $title,
                            'image_url' => $image?->image_url,
                        ];
                    })->values(),
                ];
            })->values();

            return $this->ok($categories);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener la vista virtual.');
        }
    }

    public function events(Request $request, Club $club) {
        try {
            $query = WebsiteEvent::where('club_id', $club->id);

            if ($request->filled('year')) {
                $query->whereYear('start_date', (int) $request->year);
            }

            if ($request->filled('month')) {
                $query->whereMonth('start_date', (int) $request->month);
            }

            $events = $query->orderBy('start_date')
                ->orderBy('id')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => (string) $event->id,
                        'title' => $event->title,
                        'start' => $event->start_date->format('Y-m-d'),
                        'end' => $event->end_date->format('Y-m-d'),
                        'calendarId' => $event->type,
                    ];
                });

            return $this->ok($events);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener los eventos.');
        }
    }
}
