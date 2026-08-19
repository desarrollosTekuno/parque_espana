<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachResource extends JsonResource
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
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'second_last_name' => $this->second_last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'photo' => $this->photo,
            'specialties' => $this->whenLoaded('specialties', function () {
                return $this->specialties->map(fn ($specialty) => [
                    'id' => $specialty->id,
                    'name' => $specialty->name,
                    'code' => $specialty->code,
                ])->values();
            }),
        ];
    }
}
