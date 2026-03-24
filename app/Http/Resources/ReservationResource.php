<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_time' => $this->start_datetime,
            'end_time' => $this->end_datetime,
            'cancelled_at' => $this->cancelled_at,

            'amenityResource' => [
                'id' => $this->amenityResource?->id,
                'name' => $this->amenityResource?->name,
            ],

            'amenity' => [
                'id' => $this->amenity?->id,
                'name' => $this->amenity?->name,
            ],

            'status' => [
                'id' => $this->status?->id,
                'name' => $this->status?->name,
                'color' => $this->status?->color
            ],

            'created_at' => $this->created_at,
        ];
    }
}
