<?php

namespace App\Http\Resources;

use App\Support\SpanishDate;
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
        $duration = $this->start_datetime && $this->end_datetime
            ? abs($this->end_datetime->diffInMinutes($this->start_datetime))
            : null;

        return [
            'id' => $this->id,
            'start_time' => $this->start_datetime,
            'end_time' => $this->end_datetime,
            'date' => $this->start_datetime?->format('Y-m-d'),
            'time' => $this->start_datetime?->format('H:i'),
            'date_label' => $this->start_datetime ? SpanishDate::fullDate($this->start_datetime) : null,
            'duration_minutes' => $duration,
            'cancelled_at' => $this->cancelled_at,

            'amenityResource' => [
                'id' => $this->amenityResource?->id,
                'name' => $this->amenityResource?->name,
            ],

            'amenity' => [
                'id' => $this->amenity?->id,
                'name' => $this->amenity?->name,
                'reservation_type' => $this->amenity?->reservation_type,
                'background_image_url' => $this->amenity?->background_image_url,
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
