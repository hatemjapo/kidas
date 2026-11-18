<?php

namespace App\Http\Resources;

use App\Data\DockSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DockSchedule
 */
class DockScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'is_active' => $this->isActive,
            'usage' => $this->usage,
            'bookings' => ScheduleBookingResource::collection($this->bookings),
        ];
    }
}
