<?php

namespace App\Http\Resources;

use App\Data\Slot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Slot
 */
class SlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'starts_at' => $this->startsAt->toIso8601ZuluString(),
            'ends_at' => $this->endsAt->toIso8601ZuluString(),
            'shift' => $this->shift->value,
        ];
    }
}
