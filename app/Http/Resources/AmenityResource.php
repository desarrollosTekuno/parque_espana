<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AmenityResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'reservation_type' => $this->reservation_type,
            'slot_durarion_minutes' => $this->slot_duration_minutes,
            'club_id' => $this->club_id,

            'resources' => AmenityResourceItem::collection(
                $this->whenLoaded('resources')
                ->where('is_active', true)
            ),

        ];
    }
}
