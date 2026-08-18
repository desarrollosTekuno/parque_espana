<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrolledCount = $this->active_enrollments_count ?? $this->activeEnrollments()->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'capacity' => $this->capacity,
            'enrolled_count' => $enrolledCount,
            'available_spots' => max(0, $this->capacity - $enrolledCount),
            'coach' => new CoachResource($this->whenLoaded('coach')),
        ];
    }
}
