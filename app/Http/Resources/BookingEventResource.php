<?php

namespace App\Http\Resources;

use App\Models\BookingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingEvent
 */
class BookingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'note' => $this->note,
            'by' => $this->user?->name,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
