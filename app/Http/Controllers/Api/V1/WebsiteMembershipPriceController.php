<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipType;

class WebsiteMembershipPriceController extends Controller {

    public function index(Club $club) {
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
}
