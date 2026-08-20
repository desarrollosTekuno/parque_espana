<?php

namespace App\Http\Resources;

use App\Support\SpanishDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GardenReservationResource extends JsonResource
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
            'type' => str_starts_with($this->amenityResource?->name ?? '', 'Asador') ? 'grill' : 'garden',
            'date' => $this->reservation_date,
            'date_label' => $this->start_datetime ? SpanishDate::fullDate($this->start_datetime) : null,
            'cancelled_at' => $this->cancelled_at,
            'linked_reservation_id' => $this->linked_reservation_id,

            'club' => [
                'id' => $this->club?->id,
                'name' => $this->club?->name,
            ],

            'resource' => [
                'id' => $this->amenityResource?->id,
                'name' => $this->amenityResource?->name,
            ],

            'requires_tent' => (bool) $this->requires_tent,
            'tables_count' => $this->tables_count,
            'chairs_count' => $this->chairs_count,
            'notes' => $this->notes,

            'status' => [
                'id' => $this->status?->id,
                'name' => $this->status?->name,
                'color' => $this->status?->color,
            ],

            'created_at' => $this->created_at,
        ];
    }
}
