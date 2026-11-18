<?php

namespace App\Http\Resources;

use App\Data\DaySchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DaySchedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'is_released' => $this->isReleased,
            'is_closed' => $this->isClosed,
            'opens_at' => $this->opensAt->toIso8601ZuluString(),
            'closes_at' => $this->closesAt->toIso8601ZuluString(),
            'docks' => DockScheduleResource::collection($this->docks),
        ];
    }
}
