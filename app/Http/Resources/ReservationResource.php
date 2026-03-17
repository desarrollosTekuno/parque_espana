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
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'cancelled_at' => $this->cancelled_at,

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
