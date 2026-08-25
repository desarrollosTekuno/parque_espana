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
            'icon_url' => $this->icon_url,
            'background_image_url' => $this->background_image_url,
            'regulation_file_url' => $this->regulation_file_url,
            'club_id' => $this->club_id,

            'resources' => AmenityResourceItem::collection(
                $this->whenLoaded('resources')
                ->where('is_active', true)
            ),

        ];
    }
}
